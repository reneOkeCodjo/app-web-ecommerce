/**
 * Globe terrestre d'ambiance.
 *
 * Un seul shader WebGL 1, aucun maillage 3D, aucune bibliothèque.
 * Le rendu est volontairement léger : basse résolution, 30 images/s,
 * pause automatique quand l'onglet est caché.
 */

const RAD = Math.PI / 180;
const TWO_PI = Math.PI * 2;

/**
 * Calcule la position du soleil (point subsolaire) pour une date donnée.
 *
 * @param {Date} date
 * @returns {{ declination: number, longitude: number }}
 */
export function subsolarPoint(date = new Date()) {
  const year = date.getUTCFullYear();
  const startOfYear = Date.UTC(year, 0, 0);
  const current = Date.UTC(
    year,
    date.getUTCMonth(),
    date.getUTCDate(),
    date.getUTCHours(),
    date.getUTCMinutes(),
    date.getUTCSeconds()
  );

  // Numéro du jour dans l'année (1 à 366).
  const dayOfYear = Math.floor((current - startOfYear) / 86400000);

  // Déclinaison approximative du soleil, en radians.
  const declination = -23.44 * RAD * Math.cos((TWO_PI / 365) * (dayOfYear + 10));

  // Heure UTC avec les minutes et les secondes.
  const hours =
    date.getUTCHours() +
    date.getUTCMinutes() / 60 +
    date.getUTCSeconds() / 3600;

  // Longitude sous le soleil, en radians.
  const longitude = (12 - hours) * 15 * RAD;

  return { declination, longitude };
}

/** Rotation autour de l'axe X (matrice 3x3, ordre ligne par ligne). */
function rotX(angle) {
  const c = Math.cos(angle);
  const s = Math.sin(angle);
  return [1, 0, 0, 0, c, -s, 0, s, c];
}

/** Rotation autour de l'axe Z (matrice 3x3, ordre ligne par ligne). */
function rotZ(angle) {
  const c = Math.cos(angle);
  const s = Math.sin(angle);
  return [c, -s, 0, s, c, 0, 0, 0, 1];
}

/** Multiplication de deux matrices 3x3 (ordre ligne par ligne). */
function multiply3(a, b) {
  const out = new Array(9);
  for (let row = 0; row < 3; row += 1) {
    for (let col = 0; col < 3; col += 1) {
      out[row * 3 + col] =
        a[row * 3] * b[col] +
        a[row * 3 + 1] * b[3 + col] +
        a[row * 3 + 2] * b[6 + col];
    }
  }
  return out;
}

/** Normalise un vecteur à trois dimensions. */
function normalize3(x, y, z) {
  const length = Math.hypot(x, y, z) || 1;
  return [x / length, y / length, z / length];
}

const VERTEX_SHADER = `
attribute vec2 aPosition;
varying vec2 vUv;

void main() {
  vUv = aPosition;
  gl_Position = vec4(aPosition, 0.0, 1.0);
}
`;

const FRAGMENT_SHADER = `
precision highp float;

varying vec2 vUv;

uniform sampler2D uDay;
uniform sampler2D uNight;
uniform mat3 uToEarth;
uniform vec3 uSun;
uniform float uR;
uniform float uPx;
uniform vec2 uFadeAt;

const float PI = 3.141592653589793;

void main() {
  vec2 p = vUv;
  float r = length(p);
  float a = 0.0;
  vec3 col = vec3(0.0);

  // Intérieur de la sphère : jour, nuit, lumières des villes et atmosphère.
  if (r < uR + uPx) {
    float z = sqrt(max(uR * uR - r * r, 0.0));
    vec3 n = normalize(vec3(p, z));
    vec3 e = uToEarth * n;

    float lon = atan(e.x, e.z);
    float lat = asin(clamp(e.y, -1.0, 1.0));
    vec2 uv = vec2(0.5 + lon / (2.0 * PI), 0.5 - lat / PI);

    vec3 day = texture2D(uDay, uv).rgb;
    float lights = texture2D(uNight, uv).r;
    float gray = dot(day, vec3(0.299, 0.587, 0.114));
    vec3 desat = mix(vec3(gray), day, 0.8);

    float ndl = dot(n, uSun);
    float lit = smoothstep(-0.10, 0.28, ndl);

    vec3 dayLit = desat * (0.24 + 0.9 * max(ndl, 0.0));
    vec3 cities = vec3(1.0, 0.62, 0.30) * pow(lights, 1.25) * 1.8;
    vec3 night = day * 0.06 + vec3(0.006, 0.009, 0.02) + cities;

    col = mix(night, dayLit, lit);
    col += vec3(0.85, 0.45, 0.22) * exp(-pow(ndl / 0.09, 2.0)) * 0.035;
    col += vec3(0.34, 0.56, 1.0) * pow(1.0 - n.z, 2.6)
      * (0.14 + 0.8 * smoothstep(-0.35, 0.65, ndl));

    a = 1.0 - smoothstep(uR - uPx, uR + uPx, r);
  }

  // Halo extérieur, plus lumineux du côté du soleil.
  float outside = max(r - uR, 0.0);
  vec2 sunDir = normalize(uSun.xy + vec2(0.0001));
  float sunSide = 0.25 + 0.75 * smoothstep(-0.5, 0.85, dot(p / max(r, 0.0001), sunDir));
  float halo = exp(-outside * 14.0) * sunSide * 0.55 * step(uR - uPx, r);
  vec3 haloCol = vec3(0.3, 0.5, 1.0);

  // Couleur prémultipliée : le fond de la page reste visible.
  vec3 rgb = col * a + haloCol * halo * (1.0 - a);
  float alpha = max(a, halo);

  // Fondu progressif vers le contenu, calculé dans le shader.
  float melt = 1.0 - smoothstep(0.7, 1.5, distance(p, uFadeAt));
  rgb *= melt;
  alpha *= melt;

  // Tramage pour éviter les bandes dans les dégradés sombres.
  float dither = fract(52.9829189 * fract(dot(gl_FragCoord.xy, vec2(0.06711056, 0.00583715)))) - 0.5;
  rgb = max(rgb + dither * 1.5 / 255.0, 0.0);

  gl_FragColor = vec4(rgb, max(alpha, max(rgb.r, max(rgb.g, rgb.b))));
}
`;

/** Compile un shader et renvoie une erreur lisible en cas d'échec. */
function compileShader(gl, type, source) {
  const shader = gl.createShader(type);
  if (!shader) {
    throw new Error("Shader unavailable");
  }
  gl.shaderSource(shader, source);
  gl.compileShader(shader);
  if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
    const message = gl.getShaderInfoLog(shader) || "Shader compile error";
    gl.deleteShader(shader);
    throw new Error(message);
  }
  return shader;
}

/** Crée le programme WebGL à partir des deux shaders. */
function createProgram(gl) {
  const vertex = compileShader(gl, gl.VERTEX_SHADER, VERTEX_SHADER);
  const fragment = compileShader(gl, gl.FRAGMENT_SHADER, FRAGMENT_SHADER);
  const program = gl.createProgram();
  if (!program) {
    throw new Error("Program unavailable");
  }
  gl.attachShader(program, vertex);
  gl.attachShader(program, fragment);
  gl.linkProgram(program);
  gl.deleteShader(vertex);
  gl.deleteShader(fragment);
  if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
    const message = gl.getProgramInfoLog(program) || "Program link error";
    gl.deleteProgram(program);
    throw new Error(message);
  }
  return program;
}

/** Charge une texture image et prépare ses mipmaps. */
function loadTexture(gl, url, unit) {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.decoding = "async";
    image.onload = () => {
      try {
        const texture = gl.createTexture();
        if (!texture) {
          reject(new Error("Texture unavailable"));
          return;
        }
        gl.activeTexture(gl.TEXTURE0 + unit);
        gl.bindTexture(gl.TEXTURE_2D, texture);
        gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGB, gl.RGB, gl.UNSIGNED_BYTE, image);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.REPEAT);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR_MIPMAP_LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.generateMipmap(gl.TEXTURE_2D);
        resolve(texture);
      } catch (error) {
        reject(error);
      }
    };
    image.onerror = () => reject(new Error("Texture load error"));
    image.src = url;
  });
}

/**
 * Monte le globe sur un canvas.
 *
 * @param {{
 *   canvas: HTMLCanvasElement,
 *   dayUrl: string,
 *   nightUrl: string,
 *   animate: boolean,
 *   onFirstFrame?: () => void
 * }} options
 * @returns {Promise<() => void>}
 */
export async function mountEarthGlobe(options) {
  const { canvas, dayUrl, nightUrl, animate, onFirstFrame } = options;
  const gl = canvas.getContext("webgl", {
    alpha: true,
    premultipliedAlpha: true,
    antialias: false,
    depth: false,
    stencil: false,
    powerPreference: "low-power",
  });

  if (!gl) {
    throw new Error("WebGL unavailable");
  }

  const program = createProgram(gl);
  gl.useProgram(program);

  const buffer = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
  gl.bufferData(
    gl.ARRAY_BUFFER,
    new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]),
    gl.STATIC_DRAW
  );

  const position = gl.getAttribLocation(program, "aPosition");
  gl.enableVertexAttribArray(position);
  gl.vertexAttribPointer(position, 2, gl.FLOAT, false, 0, 0);

  const uDay = gl.getUniformLocation(program, "uDay");
  const uNight = gl.getUniformLocation(program, "uNight");
  const uToEarth = gl.getUniformLocation(program, "uToEarth");
  const uSun = gl.getUniformLocation(program, "uSun");
  const uR = gl.getUniformLocation(program, "uR");
  const uPx = gl.getUniformLocation(program, "uPx");
  const uFadeAt = gl.getUniformLocation(program, "uFadeAt");

  // Les deux textures sont chargées en parallèle.
  await Promise.all([
    loadTexture(gl, dayUrl, 0),
    loadTexture(gl, nightUrl, 1),
  ]);

  gl.uniform1i(uDay, 0);
  gl.uniform1i(uNight, 1);
  gl.uniform1f(uR, 0.8);
  gl.uniform2f(uFadeAt, 0.3, -0.3);

  // Position réelle du soleil au moment du chargement.
  const subsolar = subsolarPoint(new Date());
  const sun = normalize3(
    -0.9,
    0.18 + Math.sin(subsolar.declination) * 0.6,
    -0.12
  );
  gl.uniform3f(uSun, sun[0], sun[1], sun[2]);

  // Inclinaison de la Terre et vue légèrement au-dessus.
  const base = multiply3(rotX(14 * RAD), rotZ(-18 * RAD));

  // On place la longitude de midi sous le soleil.
  const sunEarthX = base[0] * sun[0] + base[3] * sun[1] + base[6] * sun[2];
  const sunEarthY = base[1] * sun[0] + base[4] * sun[1] + base[7] * sun[2];
  const sunEarthZ = base[2] * sun[0] + base[5] * sun[1] + base[8] * sun[2];
  const spin0 = Math.atan2(sunEarthX, sunEarthZ) - subsolar.longitude;

  const omega = TWO_PI / 240;
  const toView = new Float32Array(9);
  let spin = spin0;
  let lastDraw = 0;
  let stopped = false;
  let timer = 0;
  let raf = 0;
  let period = 1000 / 60;
  let every = 2;
  let firstFrame = true;
  let density = 1;

  const scale = Math.min(window.devicePixelRatio || 1, 1.5) * 0.75;

  /** Adapte la taille du canvas à sa taille affichée. */
  function resize() {
    const rect = canvas.getBoundingClientRect();
    const width = Math.max(1, Math.round(rect.width * scale));
    const height = Math.max(1, Math.round(rect.height * scale));

    if (canvas.width !== width || canvas.height !== height) {
      canvas.width = width;
      canvas.height = height;
    }

    gl.viewport(0, 0, width, height);
    density = 2.4 / Math.max(1, Math.min(width, height));
    gl.uniform1f(uPx, density);

    if (!animate) {
      draw(performance.now(), false);
    }
  }

  /** Réécrit la matrice vue ← Terre sans allocation. */
  function updateMatrix() {
    const c = Math.cos(spin);
    const s = Math.sin(spin);

    toView[0] = base[0] * c - base[2] * s;
    toView[1] = base[1];
    toView[2] = base[0] * s + base[2] * c;
    toView[3] = base[3] * c - base[5] * s;
    toView[4] = base[4];
    toView[5] = base[3] * s + base[5] * c;
    toView[6] = base[6] * c - base[8] * s;
    toView[7] = base[7];
    toView[8] = base[6] * s + base[8] * c;

    // GLSL lit la matrice par colonnes : on lui donne la transposée.
    gl.uniformMatrix3fv(uToEarth, false, toView);
  }

  /** Dessine une image du globe. */
  function draw(now, advance) {
    if (stopped) {
      return;
    }

    if (advance) {
      const elapsed = lastDraw
        ? Math.min(now - lastDraw, 2 * every * period)
        : every * period;
      spin += (omega * elapsed) / 1000;
    }
    lastDraw = now;

    updateMatrix();
    gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);

    if (firstFrame) {
      firstFrame = false;
      if (typeof onFirstFrame === "function") {
        onFirstFrame();
      }
    }
  }

  /** Programme une image, puis la suivante. */
  function schedule() {
    if (stopped) {
      return;
    }

    timer = window.setTimeout(() => {
      if (stopped) {
        return;
      }
      raf = window.requestAnimationFrame((now) => {
        draw(now, true);
        schedule();
      });
    }, Math.max(0, (every - 0.5) * period - 2));
  }

  /** Mesure la fréquence de l'écran sur les 15 premières images. */
  function startAnimation() {
    const samples = [];
    let previous = 0;
    let count = 0;

    function measure(now) {
      if (stopped) {
        return;
      }
      if (previous > 0) {
        samples.push(now - previous);
      }
      previous = now;
      count += 1;

      if (count < 16) {
        raf = window.requestAnimationFrame(measure);
        return;
      }

      samples.sort((a, b) => a - b);
      period = samples[7] || 1000 / 60;
      every = Math.max(1, Math.round(1000 / period / 30));
      schedule();
    }

    raf = window.requestAnimationFrame(measure);
  }

  /** Arrête temporairement les réveils du navigateur. */
  function stopTimers() {
    window.clearTimeout(timer);
    window.cancelAnimationFrame(raf);
    timer = 0;
    raf = 0;
  }

  /** Reprend le rendu après un onglet caché. */
  function handleVisibility() {
    if (stopped || !animate) {
      return;
    }

    if (document.hidden) {
      stopTimers();
      return;
    }

    lastDraw = 0;
    schedule();
  }

  /** Libère complètement le globe. */
  function stop() {
    if (stopped) {
      return;
    }
    stopped = true;
    stopTimers();
    observer.disconnect();
    document.removeEventListener("visibilitychange", handleVisibility);
    canvas.removeEventListener("webglcontextlost", handleContextLost);
    const extension = gl.getExtension("WEBGL_lose_context");
    if (extension && !gl.isContextLost()) {
      extension.loseContext();
    }
  }

  /** Le navigateur a perdu le contexte graphique (mémoire, GPU…). */
  function handleContextLost(event) {
    event.preventDefault();
    stopTimers();
  }

  const observer = new ResizeObserver(() => {
    if (!stopped) {
      resize();
    }
  });
  observer.observe(canvas);

  document.addEventListener("visibilitychange", handleVisibility);
  canvas.addEventListener("webglcontextlost", handleContextLost);

  resize();

  // Première image immédiate : elle montre la vraie heure du jour.
  draw(performance.now(), false);

  if (animate) {
    startAnimation();
  }

  return stop;
}
