/*
  preloader.js
  ------------
  Anime le voile d'entrée une seule fois par session.
  Le shader reste volontairement petit et limité à 30 images par seconde.
*/

const html = document.documentElement;
const preloader = document.querySelector('#preloader');

if (html.classList.contains('is-intro') && preloader instanceof HTMLElement) {
    const canvas = preloader.querySelector('[data-preloader-canvas]');
    const bar = preloader.querySelector('[data-preloader-bar]');
    const percentLabel = preloader.querySelector('[data-preloader-percent]');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    let percent = 0;
    let finished = false;
    let frameId = 0;
    let shaderCleanup = () => {};

    // Le pourcentage est volontairement fluide mais jamais bloquant.
    const updateProgress = () => {
        const loaded = document.readyState === 'complete';
        const ceiling = loaded ? 100 : 92;
        const step = loaded ? 4 : Math.max(0.8, (ceiling - percent) * 0.035);

        percent = Math.min(ceiling, percent + step);

        if (bar instanceof HTMLElement) {
            bar.style.width = `${percent}%`;
        }

        if (percentLabel instanceof HTMLElement) {
            percentLabel.textContent = String(Math.round(percent)).padStart(2, '0');
        }
    };

    // On termine l'intro en douceur puis on libère le canvas.
    const finish = () => {
        if (finished) {
            return;
        }

        finished = true;
        percent = 100;
        updateProgress();
        preloader.classList.add('is-leaving');
        shaderCleanup();

        try {
            window.sessionStorage.setItem('atlas-intro-seen', '1');
        } catch (error) {
            // Le voile reste fonctionnel même si le stockage est bloqué.
        }

        window.setTimeout(() => {
            html.classList.remove('is-intro');
            preloader.setAttribute('aria-hidden', 'true');
        }, 620);
    };

    // Le shader dessine une nappe de lumière très légère derrière le logo.
    const startShader = () => {
        if (!(canvas instanceof HTMLCanvasElement) || reduceMotion) {
            return;
        }

        const gl = canvas.getContext('webgl', {
            alpha: false,
            antialias: false,
            depth: false,
            powerPreference: 'low-power',
        });

        if (!gl) {
            return;
        }

        const vertexSource = `
            attribute vec2 a_position;
            void main() {
                gl_Position = vec4(a_position, 0.0, 1.0);
            }
        `;

        const fragmentSource = `
            precision mediump float;
            uniform vec2 u_resolution;
            uniform float u_time;
            uniform vec2 u_pointer;

            float hash(vec2 p) {
                return fract(sin(dot(p, vec2(127.1, 311.7))) * 43758.5453);
            }

            float noise(vec2 p) {
                vec2 i = floor(p);
                vec2 f = fract(p);
                f = f * f * (3.0 - 2.0 * f);
                float a = hash(i);
                float b = hash(i + vec2(1.0, 0.0));
                float c = hash(i + vec2(0.0, 1.0));
                float d = hash(i + vec2(1.0, 1.0));
                return mix(mix(a, b, f.x), mix(c, d, f.x), f.y);
            }

            void main() {
                vec2 uv = gl_FragCoord.xy / u_resolution.xy;
                vec2 p = (uv - 0.5) * vec2(u_resolution.x / u_resolution.y, 1.0);
                p += u_pointer * 0.08;

                float t = u_time * 0.055;
                float n = noise(p * 2.4 + vec2(t, -t));
                n += noise(p * 5.2 - vec2(t * 1.4, t)) * 0.35;
                n = smoothstep(0.18, 0.92, n);

                float halo = smoothstep(0.72, 0.0, length(p - vec2(0.05, 0.02)));
                float horizon = smoothstep(0.55, 1.0, p.y + n * 0.25);

                vec3 base = vec3(0.018, 0.019, 0.03);
                vec3 blue = vec3(0.12, 0.18, 0.38);
                vec3 gold = vec3(0.84, 0.68, 0.38);

                vec3 color = mix(base, blue, n * 0.55 + horizon * 0.18);
                color += gold * halo * 0.2;
                color += vec3(0.12, 0.35, 0.28) * smoothstep(0.86, 1.0, n) * 0.4;

                gl_FragColor = vec4(color, 1.0);
            }
        `;

        const compile = (type, source) => {
            const shader = gl.createShader(type);

            if (!shader) {
                return null;
            }

            gl.shaderSource(shader, source);
            gl.compileShader(shader);

            if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
                gl.deleteShader(shader);
                return null;
            }

            return shader;
        };

        const vertexShader = compile(gl.VERTEX_SHADER, vertexSource);
        const fragmentShader = compile(gl.FRAGMENT_SHADER, fragmentSource);

        if (!vertexShader || !fragmentShader) {
            return;
        }

        const program = gl.createProgram();

        if (!program) {
            return;
        }

        gl.attachShader(program, vertexShader);
        gl.attachShader(program, fragmentShader);
        gl.linkProgram(program);

        if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
            return;
        }

        gl.useProgram(program);

        const buffer = gl.createBuffer();
        gl.bindBuffer(gl.ARRAY_BUFFER, buffer);
        gl.bufferData(
            gl.ARRAY_BUFFER,
            new Float32Array([-1, -1, 3, -1, -1, 3]),
            gl.STATIC_DRAW
        );

        const position = gl.getAttribLocation(program, 'a_position');
        gl.enableVertexAttribArray(position);
        gl.vertexAttribPointer(position, 2, gl.FLOAT, false, 0, 0);

        const resolution = gl.getUniformLocation(program, 'u_resolution');
        const time = gl.getUniformLocation(program, 'u_time');
        const pointer = gl.getUniformLocation(program, 'u_pointer');

        const resize = () => {
            const ratio = Math.min(window.devicePixelRatio || 1, 1);
            const width = Math.max(1, Math.floor(canvas.clientWidth * 0.55 * ratio));
            const height = Math.max(1, Math.floor(canvas.clientHeight * 0.55 * ratio));
            canvas.width = width;
            canvas.height = height;
            gl.viewport(0, 0, width, height);
            gl.uniform2f(resolution, width, height);
        };

        const state = { x: 0, y: 0, targetX: 0, targetY: 0 };
        const start = performance.now();
        let lastFrame = 0;

        const onPointerMove = (event) => {
            state.targetX = (event.clientX / window.innerWidth - 0.5) * 2;
            state.targetY = (event.clientY / window.innerHeight - 0.5) * -2;
        };

        const render = (now) => {
            if (finished) {
                return;
            }

            if (now - lastFrame >= 33) {
                lastFrame = now;
                state.x += (state.targetX - state.x) * 0.04;
                state.y += (state.targetY - state.y) * 0.04;

                gl.uniform1f(time, (now - start) / 1000);
                gl.uniform2f(pointer, state.x, state.y);
                gl.drawArrays(gl.TRIANGLES, 0, 3);
            }

            frameId = window.requestAnimationFrame(render);
        };

        resize();
        window.addEventListener('resize', resize, { passive: true });
        window.addEventListener('pointermove', onPointerMove, { passive: true });
        frameId = window.requestAnimationFrame(render);

        shaderCleanup = () => {
            window.cancelAnimationFrame(frameId);
            window.removeEventListener('resize', resize);
            window.removeEventListener('pointermove', onPointerMove);
            gl.getExtension('WEBGL_lose_context')?.loseContext();
        };
    };

    // On laisse le navigateur peindre le voile avant de lancer le shader.
    window.setTimeout(startShader, 40);

    const interval = window.setInterval(updateProgress, 80);

    const finishWhenReady = () => {
        window.setTimeout(() => {
            window.clearInterval(interval);
            finish();
        }, reduceMotion ? 180 : 900);
    };

    if (document.readyState === 'complete') {
        finishWhenReady();
    } else {
        window.addEventListener('load', finishWhenReady, { once: true });
    }

    // Filet de sécurité : on ne bloque jamais la page plus de 3,2 secondes.
    window.setTimeout(() => {
        window.clearInterval(interval);
        finish();
    }, 3200);
}
