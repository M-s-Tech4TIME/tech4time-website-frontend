/* ==========================================================================
   Tech4TIME — neural.js
   Brings the home hero's neural mesh to life: clusters of nodes that travel
   the whole hero, mill about inside themselves, and link to whatever comes
   within reach — so the chunks hold together, drift past one another, and are
   never the same shape twice.

   THE MESH IS AN ENHANCEMENT AND NOTHING ELSE
   There is no mesh in the markup. This module builds the whole thing — its
   container included — and takes it away again when it should not be there.
   With JavaScript off the hero is a plain hero: the headline, the badges, the
   terminal, and nothing behind them. That is the intended appearance, not a
   degraded one, and nothing here carries meaning so nothing is lost with it.

   THREE STATES, NOT TWO
     scripting off        nothing at all — no canvas, no container, no markup
     reduced motion       the same picture, drawn once and never again
     otherwise            the picture, moving

   Reduced motion asks for stillness, not for blankness. So the mesh is drawn
   exactly as it would be on any other frame and then left alone: no loop, no
   timer, nothing scheduled. It is redrawn only when the geometry or the
   palette actually changes under it — a resize, or a change of theme.

   WHY A CANVAS, WHEN EVERYTHING ELSE HERE IS CSS
   A CSS animation interpolates fixed properties on fixed elements: the browser
   must know at parse time that a line runs from A to B. A link between two
   wandering nodes has no such endpoints — where it lands depends on where both
   nodes happen to be this instant. Canvas has no elements at all. Every frame
   is cleared and redrawn from current positions, so a link is simply
   "if these two are close enough, draw one", recomputed sixty times a second.
   Links appear as nodes drift together and vanish as they part, which is the
   whole effect and cannot be had any other way.

   WHAT IT COSTS, AND WHAT PAYS FOR IT
   A continuous requestAnimationFrame loop on the site's most visited page is
   not free, so it stops rather than idles: when the hero scrolls out of view,
   when the tab is hidden, and when someone asks for reduced motion. Colour is
   read from the same custom properties the SVG uses, and re-read whenever the
   theme changes, because a canvas cannot inherit a token the way a stylesheet
   can — and no check in this repository can see canvas pixels, so getting that
   wrong would be invisible.
   ========================================================================== */

(function (global) {
  "use strict";

  var doc = global.document;

  /* One node per this many square pixels, within the bounds below. Tuned so a
     phone carries a calm field and a wide desktop a busy one. */
  var AREA_PER_NODE = 8200;
  var MIN_NODES = 26;
  var MAX_NODES = 150;

  /* Nodes belong to clusters, and clusters travel. A uniform field of points
     reads as static noise however fast it moves; chunks that hold loosely
     together, drift about the hero and occasionally brush past one another
     read as a network. */
  var NODES_PER_CLUSTER = 13;
  var MIN_CLUSTERS = 3;
  var MAX_CLUSTERS = 11;
  var FREE_SHARE = 0.14;      /* nodes belonging to no cluster, wandering */

  var SPEED_MIN = 5;          /* a node, relative to its cluster, px/s */
  var SPEED_MAX = 13;
  var DRIFT_MIN = 9;          /* a whole cluster, across the hero, px/s */
  var DRIFT_MAX = 20;
  var TURN = 0.6;             /* radians per second — how much a node mills */
  var CLUSTER_TURN = 0.09;    /* a cluster changes course far more slowly */
  var COHESION = 1.7;         /* pull back per px beyond the cluster radius */
  var PULSES = 12;
  var PULSE_SPEED_MIN = 0.22; /* fraction of a link per second */
  var PULSE_SPEED_MAX = 0.55;
  var PULSE_LEN = 0.16;       /* fraction of the link the bright part covers */

  function rand(lo, hi) {
    return lo + Math.random() * (hi - lo);
  }

  /* ---------------------------------------------------------------- Mesh */

  function Mesh(hero, still) {
    this.hero = hero;
    /* Set once, for the life of this instance. Switching between still and
       moving replaces the instance rather than mutating it, so there is no
       state that can be half-way between the two. */
    this.still = !!still;
    this.watchers = [];
    /* Built here rather than in the markup, so a page with no JavaScript has
       no empty decoration box sitting in its hero. */
    this.root = doc.createElement("div");
    this.root.className = "hero-neural";
    this.root.setAttribute("aria-hidden", "true");

    this.canvas = doc.createElement("canvas");
    this.canvas.className = "hero-neural__canvas";
    this.canvas.setAttribute("aria-hidden", "true");
    this.ctx = this.canvas.getContext("2d");

    this.nodes = [];
    this.clusters = [];
    this.pulses = [];
    this.pairs = [];            /* reused every frame: a, b, a, b, ... */
    this.pairCount = 0;
    this.frame = 0;
    this.last = 0;
    this.running = false;
    this.visible = true;
    this.w = 0;
    this.h = 0;
    this.link = 140;
    this.radius = 120;

    this.ink = {link: "#888", node: "#888", signal: "#fff",
                linkAlpha: 0.24, nodeAlpha: 0.36, signalAlpha: 0.55};

    this.tick = this.tick.bind(this);
    this.onResize = this.onResize.bind(this);
    this.onVisibility = this.onVisibility.bind(this);
  }

  /* Colour comes from the container's custom properties, so the canvas and the
     SVG it replaces are painted from one source. A custom property is resolved
     by the time it is read, so var(--accent-text) arrives here as a colour. */
  Mesh.prototype.readInk = function () {
    var cs = global.getComputedStyle(this.root);
    function prop(name, fallback) {
      var v = cs.getPropertyValue(name);
      v = v ? v.trim() : "";
      return v || fallback;
    }
    function num(name, fallback) {
      var v = parseFloat(prop(name, ""));
      return isNaN(v) ? fallback : v;
    }
    var fallback = global.getComputedStyle(doc.documentElement).color || "#888";
    this.ink = {
      link: prop("--neural-link", fallback),
      node: prop("--neural-node", fallback),
      signal: prop("--neural-signal", fallback),
      linkAlpha: num("--neural-link-opacity", 0.24),
      nodeAlpha: num("--neural-node-opacity", 0.36),
      signalAlpha: num("--neural-signal-opacity", 0.55)
    };
  };

  Mesh.prototype.measure = function () {
    var box = this.root.getBoundingClientRect();
    var w = Math.max(1, Math.round(box.width));
    var h = Math.max(1, Math.round(box.height));
    var dpr = Math.min(global.devicePixelRatio || 1, 2);

    var had = this.w && this.h;
    var sx = had ? w / this.w : 1;
    var sy = had ? h / this.h : 1;

    this.w = w;
    this.h = h;
    this.canvas.width = Math.round(w * dpr);
    this.canvas.height = Math.round(h * dpr);
    this.ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

    /* Cluster size and link reach are derived together, so the mesh reads the
       same at any size: a cluster is a fraction of the space available to it,
       and a link reaches a little less far than a cluster is wide — enough to
       web a cluster together, not enough to join everything to everything. */
    var want = this.want();
    var groups = Math.max(MIN_CLUSTERS,
                 Math.min(MAX_CLUSTERS, Math.round(want / NODES_PER_CLUSTER)));
    this.radius = Math.max(56, Math.min(130, Math.sqrt((w * h) / groups) * 0.30));
    this.link = Math.max(62, Math.min(150, this.radius * 1.05));

    if (had) {
      for (var i = 0; i < this.nodes.length; i++) {
        this.nodes[i].x *= sx;
        this.nodes[i].y *= sy;
      }
      for (i = 0; i < this.clusters.length; i++) {
        this.clusters[i].x *= sx;
        this.clusters[i].y *= sy;
      }
    }
    this.populate(want, groups);

    /* A moving mesh redraws on its next frame anyway; a still one has no next
       frame, so a resize would otherwise leave it stretched or blank. */
    if (this.still) { this.render(); }
  };

  Mesh.prototype.want = function () {
    var n = Math.round((this.w * this.h) / AREA_PER_NODE);
    return Math.max(MIN_NODES, Math.min(MAX_NODES, n));
  };

  Mesh.prototype.populate = function (want, groups) {
    var i;

    while (this.clusters.length > groups) { this.clusters.pop(); }

    /* Seeded on a jittered grid rather than at random. Random placement puts
       three clusters in one corner about as often as not, and the first thing
       a visitor sees should not be a coin toss. They wander off the grid
       within seconds and never return to it. */
    var cols = Math.max(1, Math.round(Math.sqrt(groups * (this.w / Math.max(1, this.h)))));
    var rows = Math.ceil(groups / cols);
    while (this.clusters.length < groups) {
      var slot = this.clusters.length;
      var cw = this.w / cols;
      var ch = this.h / rows;
      this.clusters.push({
        x: (slot % cols) * cw + rand(cw * 0.25, cw * 0.75),
        y: Math.floor(slot / cols) * ch + rand(ch * 0.25, ch * 0.75),
        a: rand(0, Math.PI * 2),
        s: rand(DRIFT_MIN, DRIFT_MAX),
        turn: rand(-CLUSTER_TURN, CLUSTER_TURN),
        vx: 0,
        vy: 0
      });
    }

    while (this.nodes.length > want) { this.nodes.pop(); }
    while (this.nodes.length < want) {
      var free = Math.random() < FREE_SHARE;
      var c = free ? -1 : (Math.random() * this.clusters.length) | 0;
      var home = free ? null : this.clusters[c];
      /* sqrt keeps the placement even over the disc rather than piling every
         node onto the centre. */
      var rr = this.radius * Math.sqrt(Math.random());
      var ra = rand(0, Math.PI * 2);
      this.nodes.push({
        c: c,
        x: home ? home.x + Math.cos(ra) * rr : rand(0, this.w),
        y: home ? home.y + Math.sin(ra) * rr : rand(0, this.h),
        a: rand(0, Math.PI * 2),
        s: rand(SPEED_MIN, SPEED_MAX),
        turn: rand(-TURN, TURN),
        r: Math.random() < 0.18 ? rand(2.6, 3.6) : rand(1.5, 2.4)
      });
    }

    /* A resize can leave a node pointing at a cluster that no longer exists. */
    for (i = 0; i < this.nodes.length; i++) {
      if (this.nodes[i].c >= this.clusters.length) {
        this.nodes[i].c = this.clusters.length
          ? (Math.random() * this.clusters.length) | 0 : -1;
      }
    }

    while (this.pulses.length < PULSES) {
      this.pulses.push({a: -1, b: -1, t: 1, s: 0});
    }
  };

  Mesh.prototype.step = function (dt) {
    var i, n, c, dx, dy, d, pull, vx, vy;
    var pad = this.link;
    var edge = this.radius * 0.3;

    /* Clusters travel the hero and turn away at the edges. A centre is never
       drawn, so a bounce cannot be seen — only the chunk changing course. */
    for (i = 0; i < this.clusters.length; i++) {
      c = this.clusters[i];
      c.a += c.turn * dt;
      c.vx = Math.cos(c.a) * c.s;
      c.vy = Math.sin(c.a) * c.s;
      c.x += c.vx * dt;
      c.y += c.vy * dt;
      if (c.x < edge)              { c.x = edge;              c.a = Math.PI - c.a; }
      if (c.x > this.w - edge)     { c.x = this.w - edge;     c.a = Math.PI - c.a; }
      if (c.y < edge)              { c.y = edge;              c.a = -c.a; }
      if (c.y > this.h - edge)     { c.y = this.h - edge;     c.a = -c.a; }
    }

    for (i = 0; i < this.nodes.length; i++) {
      n = this.nodes[i];
      n.a += n.turn * dt;
      vx = Math.cos(n.a) * n.s;
      vy = Math.sin(n.a) * n.s;

      if (n.c >= 0) {
        c = this.clusters[n.c];
        /* Carried by its cluster, so the chunk travels as a body... */
        vx += c.vx;
        vy += c.vy;
        /* ...and reeled back in only once it strays past the radius, so
           inside that disc it is free to mill about and remake the shape. */
        dx = c.x - n.x;
        dy = c.y - n.y;
        d = Math.sqrt(dx * dx + dy * dy) || 1;
        if (d > this.radius) {
          pull = (d - this.radius) * COHESION;
          vx += (dx / d) * pull;
          vy += (dy / d) * pull;
        }
      }

      n.x += vx * dt;
      n.y += vy * dt;

      /* Only the unattached wrap; a clustered node is held by its centre. */
      if (n.c < 0) {
        if (n.x < -pad) { n.x = this.w + pad; }
        if (n.x > this.w + pad) { n.x = -pad; }
        if (n.y < -pad) { n.y = this.h + pad; }
        if (n.y > this.h + pad) { n.y = -pad; }
      }
    }
  };

  Mesh.prototype.draw = function (dt) {
    var ctx = this.ctx;
    var nodes = this.nodes;
    var link = this.link;
    var link2 = link * link;
    var i, j, a, b, dx, dy, d2, d, t;

    ctx.clearRect(0, 0, this.w, this.h);
    ctx.lineCap = "round";

    /* --- links, and the pair list the pulses draw from ------------------ */
    this.pairCount = 0;
    ctx.strokeStyle = this.ink.link;
    ctx.lineWidth = 1;
    for (i = 0; i < nodes.length; i++) {
      a = nodes[i];
      for (j = i + 1; j < nodes.length; j++) {
        b = nodes[j];
        dx = a.x - b.x;
        dy = a.y - b.y;
        d2 = dx * dx + dy * dy;
        if (d2 > link2) { continue; }

        d = Math.sqrt(d2);
        /* Squared falloff: a link arrives and leaves softly instead of
           snapping on at the threshold. */
        t = 1 - d / link;
        ctx.globalAlpha = this.ink.linkAlpha * t * t;
        ctx.beginPath();
        ctx.moveTo(a.x, a.y);
        ctx.lineTo(b.x, b.y);
        ctx.stroke();

        this.pairs[this.pairCount++] = i;
        this.pairs[this.pairCount++] = j;
      }
    }

    /* --- signals travelling the links ----------------------------------- */
    ctx.strokeStyle = this.ink.signal;
    ctx.lineWidth = 1.8;
    for (i = 0; i < this.pulses.length; i++) {
      var p = this.pulses[i];
      p.t += p.s * dt;

      a = nodes[p.a];
      b = nodes[p.b];
      var alive = a && b && p.t <= 1;
      if (alive) {
        dx = a.x - b.x;
        dy = a.y - b.y;
        alive = dx * dx + dy * dy <= link2;
      }
      if (!alive) {
        if (this.pairCount < 2) { continue; }
        var k = (Math.random() * (this.pairCount / 2)) | 0;
        p.a = this.pairs[k * 2];
        p.b = this.pairs[k * 2 + 1];
        /* Scattered along their links when held still, so the one frame
           reads as a mesh mid-signal rather than as a row of dots at every
           link's start. */
        p.t = this.still ? Math.random() * 0.9 : 0;
        p.s = rand(PULSE_SPEED_MIN, PULSE_SPEED_MAX);
        a = nodes[p.a];
        b = nodes[p.b];
      }

      var t0 = Math.max(0, p.t - PULSE_LEN);
      var t1 = p.t;
      d = Math.sqrt((a.x - b.x) * (a.x - b.x) + (a.y - b.y) * (a.y - b.y));
      /* Fades with the link it rides, so a signal never outlives its wire. */
      ctx.globalAlpha = this.ink.signalAlpha * Math.max(0, 1 - d / link);
      ctx.beginPath();
      ctx.moveTo(a.x + (b.x - a.x) * t0, a.y + (b.y - a.y) * t0);
      ctx.lineTo(a.x + (b.x - a.x) * t1, a.y + (b.y - a.y) * t1);
      ctx.stroke();
    }

    /* --- nodes ----------------------------------------------------------- */
    ctx.fillStyle = this.ink.node;
    ctx.globalAlpha = this.ink.nodeAlpha;
    for (i = 0; i < nodes.length; i++) {
      a = nodes[i];
      ctx.beginPath();
      ctx.arc(a.x, a.y, a.r, 0, Math.PI * 2);
      ctx.fill();
    }
    ctx.globalAlpha = 1;
  };

  /* One frame and nothing scheduled. dt of zero advances no motion, so this is
     the same drawing the loop would produce, held. */
  Mesh.prototype.render = function () {
    this.draw(0);
  };

  Mesh.prototype.tick = function (now) {
    if (!this.running) { return; }
    /* Clamped: coming back to a backgrounded tab hands you a gap of seconds,
       and every node would jump across the hero at once. */
    var dt = Math.min(0.05, (now - this.last) / 1000 || 0);
    this.last = now;
    this.step(dt);
    this.draw(dt);
    this.frame = global.requestAnimationFrame(this.tick);
  };

  Mesh.prototype.start = function () {
    if (this.running || !this.visible || this.still) { return; }
    this.running = true;
    this.last = global.performance ? global.performance.now() : Date.now();
    this.frame = global.requestAnimationFrame(this.tick);
  };

  Mesh.prototype.stop = function () {
    this.running = false;
    if (this.frame) {
      global.cancelAnimationFrame(this.frame);
      this.frame = 0;
    }
  };

  Mesh.prototype.onResize = function () {
    var self = this;
    if (this.pending) { return; }
    this.pending = global.requestAnimationFrame(function () {
      self.pending = 0;
      self.measure();
    });
  };

  Mesh.prototype.onVisibility = function () {
    if (doc.hidden) {
      this.stop();
    } else {
      this.start();
    }
  };

  Mesh.prototype.attach = function () {
    var self = this;

    /* In the document before the ink is read: the custom properties it reads
       are declared on .hero-neural, and a detached element has no computed
       style to give them. */
    this.hero.appendChild(this.root);
    this.readInk();
    this.root.appendChild(this.canvas);
    this.measure();

    global.addEventListener("resize", this.onResize);

    /* The theme can change under a canvas without the canvas hearing about it,
       which is the one way this could end up painting light-mode colours on a
       dark page. Both routes are watched: the toggle sets an attribute, and
       the system preference fires its own event. A still mesh has to be
       repainted by hand; a moving one picks it up on its next frame. */
    function repaint() {
      self.readInk();
      if (self.still) { self.render(); }
    }

    if (global.MutationObserver) {
      var mo = new global.MutationObserver(repaint);
      mo.observe(doc.documentElement,
                 {attributes: true, attributeFilter: ["data-theme"]});
      this.watchers.push(function () { mo.disconnect(); });
    }
    var dark = global.matchMedia("(prefers-color-scheme: dark)");
    if (dark.addEventListener) {
      dark.addEventListener("change", repaint);
      this.watchers.push(function () {
        dark.removeEventListener("change", repaint);
      });
    } else if (dark.addListener) {
      dark.addListener(repaint);
      this.watchers.push(function () { dark.removeListener(repaint); });
    }

    /* Nothing below this line matters to a still mesh: there is no loop to
       pause, so there is nothing to watch for. */
    if (this.still) {
      return;
    }

    doc.addEventListener("visibilitychange", this.onVisibility);

    /* Off screen is off. A loop that runs while nobody is looking at it is
       just a battery cost. */
    if (global.IntersectionObserver) {
      var io = new global.IntersectionObserver(function (entries) {
        self.visible = entries[0].isIntersecting;
        if (self.visible) { self.start(); } else { self.stop(); }
      }, {rootMargin: "80px"});
      io.observe(this.root);
      this.watchers.push(function () { io.disconnect(); });
    }
    this.start();
  };

  Mesh.prototype.detach = function () {
    this.stop();
    global.removeEventListener("resize", this.onResize);
    doc.removeEventListener("visibilitychange", this.onVisibility);
    if (this.pending) {
      global.cancelAnimationFrame(this.pending);
      this.pending = 0;
    }
    /* Every observer and media listener this instance added, undone. Reduced
       motion can be switched on and off all afternoon, and each switch builds
       a new instance; without this, the old ones would go on listening and
       repainting a canvas that is no longer in the page. */
    while (this.watchers.length) {
      this.watchers.pop()();
    }
    /* The container goes with it, so the hero is left exactly as the markup
       has it. */
    if (this.root.parentNode) {
      this.root.parentNode.removeChild(this.root);
    }
  };

  /* ----------------------------------------------------------------- api */

  var api = (global.Tech4Time = global.Tech4Time || {});

  api.neural = {
    init: function () {
      var hero = doc.querySelector(".hero");
      if (!hero || !doc.createElement("canvas").getContext) {
        return;
      }

      var calm = global.matchMedia("(prefers-reduced-motion: reduce)");
      var mesh = null;

      /* Reduced motion can be turned on and off while the page is open, so
         the mesh follows it rather than being decided once at load. The
         picture is the same either way; only whether it moves changes, and
         that is fixed per instance, so a change of mind rebuilds. */
      function sync() {
        var wantStill = calm.matches;
        if (mesh && mesh.still !== wantStill) {
          mesh.detach();
          mesh = null;
        }
        if (!mesh) {
          mesh = new Mesh(hero, wantStill);
          mesh.attach();
        }
      }

      if (calm.addEventListener) {
        calm.addEventListener("change", sync);
      } else if (calm.addListener) {
        calm.addListener(sync);
      }
      sync();
    }
  };
})(window);
