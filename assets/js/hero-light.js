/*
 * Úvodní fotka jako betonová stěrka pod „bočním světlem“ (WebGL, bez knihoven).
 * Světlo nízko nad povrchem sleduje myš (na mobilu pomalu krouží) a vykreslí reliéf stěrky:
 * reliéf se bere z jasu fotky + jemná struktura tahů hladítka.
 * Bez WebGL, při „omezit pohyb“ nebo při chybě zůstává obyčejná fotka (<img> se nikdy neodstraňuje).
 */
(function () {
  var img = document.querySelector('.hero__img');
  if (!img || img.tagName !== 'IMG') return;
  if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var VERT = 'attribute vec2 p;varying vec2 v;void main(){v=p*.5+.5;v.y=1.-v.y;gl_Position=vec4(p,0.,1.);}';
  var FRAG = [
    'precision mediump float;',
    'uniform sampler2D t;uniform vec2 px,sc,of,lp;uniform float tm,amt;varying vec2 v;',
    'float h(vec2 q){return fract(sin(dot(q,vec2(127.1,311.7)))*43758.5453);}',
    'float n(vec2 q){vec2 i=floor(q),f=fract(q);f=f*f*(3.-2.*f);',
    ' return mix(mix(h(i),h(i+vec2(1,0)),f.x),mix(h(i+vec2(0,1)),h(i+vec2(1,1)),f.x),f.y);}',
    // tahy hladítka: protažený šum, mírně natočený
    'float tr(vec2 q){mat2 r=mat2(.92,.39,-.39,.92);q=r*q*vec2(9.,26.);return n(q)*.6+n(q*2.3+7.)*.3+n(q*5.1+3.)*.1;}',
    'float lu(vec2 q){return dot(texture2D(t,q).rgb,vec3(.299,.587,.114));}',
    'void main(){',
    ' vec2 q=v*sc+of;',
    ' vec3 c=texture2D(t,q).rgb;',
    ' vec2 d=px*2.5;',
    ' vec2 g=vec2(lu(q+vec2(d.x,0.))-lu(q-vec2(d.x,0.)),lu(q+vec2(0.,d.y))-lu(q-vec2(0.,d.y)));',
    ' float e=.004;vec2 m=vec2(tr(v+vec2(e,0.))-tr(v-vec2(e,0.)),tr(v+vec2(0.,e))-tr(v-vec2(0.,e)));',
    ' vec3 N=normalize(vec3(-(g*3.3+m*.95)*amt,1.));',
    // světlo: směr z bodu nad povrchem k pixelu, nízko (raking)
    ' vec3 L=normalize(vec3(lp-v,.32));',
    ' float df=max(dot(N,L),0.);',
    ' float sp=pow(max(dot(reflect(-L,N),vec3(0.,0.,1.)),0.),36.)*.1;',
    ' float fall=.9+.16*exp(-dot(lp-v,lp-v)*2.6);',
    ' vec3 o=c*(.62+.5*df)*fall+sp*vec3(1.,.97,.92);',
    ' gl_FragColor=vec4(mix(c,o,amt),1.);',
    '}'
  ].join('\n');

  var start = function () {
    var cv = document.createElement('canvas');
    cv.className = 'hero__gl';
    cv.setAttribute('aria-hidden', 'true');
    var gl = cv.getContext('webgl', { alpha: false, antialias: false, premultipliedAlpha: false, powerPreference: 'low-power' });
    if (!gl) return;
    var sh = function (type, src) {
      var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s);
      if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(s));
      return s;
    };
    var pr;
    try {
      pr = gl.createProgram();
      gl.attachShader(pr, sh(gl.VERTEX_SHADER, VERT));
      gl.attachShader(pr, sh(gl.FRAGMENT_SHADER, FRAG));
      gl.linkProgram(pr);
      if (!gl.getProgramParameter(pr, gl.LINK_STATUS)) return;
      gl.useProgram(pr);
      var buf = gl.createBuffer();
      gl.bindBuffer(gl.ARRAY_BUFFER, buf);
      gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW);
      var loc = gl.getAttribLocation(pr, 'p');
      gl.enableVertexAttribArray(loc);
      gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);
      var tex = gl.createTexture();
      gl.bindTexture(gl.TEXTURE_2D, tex);
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, img);   // cizí doména bez CORS → výjimka → fotka zůstane
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
      gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    } catch (err) { return; }

    var U = {};
    ['px', 'sc', 'of', 'lp', 'tm', 'amt'].forEach(function (k) { U[k] = gl.getUniformLocation(pr, k); });
    var iw = img.naturalWidth, ih = img.naturalHeight;
    gl.uniform2f(U.px, 1 / iw, 1 / ih);

    var box = img.parentNode;
    box.insertBefore(cv, img.nextSibling);

    // object-fit: cover → výřez textury podle poměru stran plátna
    var fit = function () {
      var r = img.getBoundingClientRect(), dpr = Math.min(window.devicePixelRatio || 1, 1.5);
      cv.width = Math.max(1, Math.round(r.width * dpr)); cv.height = Math.max(1, Math.round(r.height * dpr));
      cv.style.width = r.width + 'px'; cv.style.height = r.height + 'px';
      gl.viewport(0, 0, cv.width, cv.height);
      var ca = r.width / r.height, ia = iw / ih, sx = 1, sy = 1;
      if (ia > ca) sx = ca / ia; else sy = ia / ca;
      gl.uniform2f(U.sc, sx, sy); gl.uniform2f(U.of, (1 - sx) / 2, (1 - sy) / 2);
      dirty = true;
    };

    // světlo: cíl podle ukazatele, plynulé dotahování; bez myši pomalé kroužení
    var cur = { x: .78, y: .18 }, tgt = { x: .78, y: .18 }, amt = 0, pointerAt = 0, visible = false, raf = 0, dirty = true, t0 = performance.now();
    var hero = box.closest('.hero') || box;
    var finePointer = window.matchMedia && matchMedia('(pointer: fine)').matches;
    hero.addEventListener('pointermove', function (ev) {
      var r = img.getBoundingClientRect();
      tgt.x = Math.max(-.4, Math.min(1.4, (ev.clientX - r.left) / r.width));
      tgt.y = Math.max(-.4, Math.min(1.4, (ev.clientY - r.top) / r.height));
      pointerAt = performance.now(); loop();
    }, { passive: true });

    var lastDraw = 0;
    var frame = function (now) {
      raf = 0;
      if (!visible || document.hidden) return;
      // myš: světlo stojí, kde ji uživatel nechal (bez překreslování); dotyk: pomalé kroužení, max ~30 snímků/s
      var idle = !finePointer;
      if (idle && now - lastDraw < 32) { loop(); return; }
      lastDraw = now;
      if (idle) {                                       // pomalé „obcházení“ s lampou
        var a = (now - t0) / 7000;
        tgt.x = .5 + Math.cos(a) * .62; tgt.y = .42 + Math.sin(a * 1.3) * .5;
      }
      var k = idle ? .03 : .12;
      cur.x += (tgt.x - cur.x) * k; cur.y += (tgt.y - cur.y) * k;
      amt += (1 - amt) * .05;                            // efekt nabíhá postupně z čisté fotky
      gl.uniform2f(U.lp, cur.x, cur.y);
      gl.uniform1f(U.amt, amt);
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
      if (!cv.classList.contains('is-on')) cv.classList.add('is-on');
      var settled = Math.abs(tgt.x - cur.x) < .001 && Math.abs(tgt.y - cur.y) < .001 && amt > .995;
      // s myší kreslit jen při pohybu; bez myši (mobil) kroužit stále, ale jen když je hero vidět
      if (!settled || idle || dirty) { dirty = false; loop(); }   // s myší se po dotažení světla smyčka zastaví
    };
    var loop = function () { if (!raf && visible && !document.hidden) raf = requestAnimationFrame(frame); };

    fit();
    if ('ResizeObserver' in window) new ResizeObserver(fit).observe(img); else window.addEventListener('resize', fit);
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(function (en) { visible = en[0].isIntersecting; loop(); }, { rootMargin: '80px' }).observe(box);
    } else { visible = true; loop(); }
    document.addEventListener('visibilitychange', loop);
    cv.addEventListener('webglcontextlost', function (e) { e.preventDefault(); cv.remove(); });
  };

  if (img.complete && img.naturalWidth) start();
  else img.addEventListener('load', start, { once: true });
})();
