<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AUTOFIX IA · Gestión de taller automotriz</title>
    <link rel="icon" href="/images/autofix-logo.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green-50: #f0fdf4;
            --green-100: #dcfce7;
            --green-200: #bbf7d0;
            --green-400: #4ade80;
            --green-500: #22c55e;
            --green-600: #16a34a;
            --green-700: #15803d;
            --green-800: #166534;
            --green-900: #14532d;
            --green-950: #052e16;
            --ink: #0f172a;
            --ink-strong: #020617;
            --muted: #475569;
            --muted-strong: #334155;
            --line: #e2e8f0;
            --bg: #f8fafc;
            --font: 'Public Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: var(--font);
            color: var(--ink);
            background: var(--bg);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        a { color: inherit; text-decoration: none; }

        .container { width: 100%; max-width: 1120px; margin: 0 auto; padding: 0 1.5rem; }

        /* Navbar */
        .nav {
            position: sticky;
            top: 0;
            z-index: 40;
            background: rgb(255 255 255 / .85);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--line);
        }

        .nav .container { display: flex; align-items: center; justify-content: space-between; height: 4rem; }

        .brand { display: flex; align-items: center; gap: .65rem; font-weight: 800; font-size: 1.1rem; letter-spacing: -.01em; }
        .brand img { width: 2.25rem; height: 2.25rem; }
        .brand .accent { color: var(--green-600); }

        .nav-links { display: flex; align-items: center; gap: .5rem; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            padding: .6rem 1.15rem;
            border-radius: .6rem;
            font-weight: 600;
            font-size: .9rem;
            border: 1px solid transparent;
            cursor: pointer;
            transition: background-color 180ms ease, color 180ms ease, border-color 180ms ease, transform 180ms ease;
        }

        .btn:hover { transform: translateY(-1px); }

        .btn-outline { border-color: var(--line); color: var(--ink); background: #fff; }
        .btn-outline:hover { border-color: var(--green-400); color: var(--green-700); }

        .btn-primary { background: var(--green-600); color: #fff; box-shadow: 0 10px 24px -14px var(--green-600); }
        .btn-primary:hover { background: var(--green-700); }

        .btn-lg { padding: .85rem 1.6rem; font-size: 1rem; border-radius: .7rem; }

        /* Hero */
        .hero {
            position: relative;
            isolation: isolate;
            overflow: hidden;
            color: #fff;
            background-color: #0b1f17;
            border-bottom: 1px solid var(--green-800);
        }

        .hero::before {
            position: absolute;
            inset: 0;
            z-index: -2;
            content: '';
            background: url('/images/taller-automatizado.jpg') center / cover no-repeat;
        }

        .hero::after {
            position: absolute;
            inset: 0;
            z-index: -1;
            content: '';
            background:
                linear-gradient(100deg, rgb(2 20 13 / .94) 0%, rgb(5 32 22 / .86) 42%, rgb(8 42 29 / .62) 100%),
                radial-gradient(circle at 78% 20%, rgb(34 197 94 / .22), transparent 24rem);
        }

        .hero .container {
            display: grid;
            grid-template-columns: 1.1fr .9fr;
            gap: 3rem;
            align-items: center;
            padding-block: 5.5rem;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .35rem .9rem;
            border-radius: 999px;
            background: rgb(187 247 208 / .14);
            color: #d1fae5;
            font-size: .8rem;
            font-weight: 700;
            border: 1px solid rgb(74 222 128 / .4);
            backdrop-filter: blur(4px);
            margin-bottom: 1.25rem;
        }

        .hero-badge .dot {
            width: .5rem;
            height: .5rem;
            border-radius: 999px;
            background: #4ade80;
            box-shadow: 0 0 0 3px rgb(74 222 128 / .3);
        }

        .hero h1 { font-size: clamp(2.2rem, 5vw, 3.4rem); line-height: 1.08; letter-spacing: -.03em; font-weight: 800; }
        .hero h1 .accent { color: #4ade80; }

        .hero p.lead { margin-top: 1.1rem; font-size: 1.08rem; color: rgb(226 232 240 / .94); max-width: 34rem; }

        .hero-cta { margin-top: 2rem; display: flex; flex-wrap: wrap; gap: .8rem; }

        .hero-meta { margin-top: 2.4rem; display: flex; flex-wrap: wrap; gap: 1.4rem; }
        .hero-meta div { display: flex; flex-direction: column; }
        .hero-meta strong { font-size: 1.15rem; font-weight: 800; color: #4ade80; }
        .hero-meta span { font-size: .78rem; color: rgb(226 232 240 / .82); text-transform: uppercase; letter-spacing: .06em; font-weight: 600; }

        .hero .btn-outline { border-color: rgb(255 255 255 / .5); color: #fff; background: rgb(255 255 255 / .1); backdrop-filter: blur(4px); }
        .hero .btn-outline:hover { border-color: #4ade80; color: #bbf7d0; background: rgb(255 255 255 / .16); }

        .hero-visual { position: relative; display: flex; justify-content: center; }
        .hero-visual .logo-wrap {
            width: min(22rem, 100%);
            border-radius: 1.4rem;
            border: 1px solid rgb(255 255 255 / .5);
            background: #fff;
            padding: 2rem;
            box-shadow: 0 30px 60px -35px rgb(0 0 0 / .6);
        }
        .hero-visual .logo-wrap img { width: 100%; height: auto; }

        /* Sections */
        section { padding-block: 4.5rem; }

        .section-head { text-align: center; max-width: 42rem; margin: 0 auto 3rem; }
        .section-head .kicker { color: var(--green-700); font-weight: 800; font-size: .8rem; text-transform: uppercase; letter-spacing: .1em; }
        .section-head h2 { font-size: clamp(1.7rem, 3.5vw, 2.4rem); letter-spacing: -.02em; font-weight: 800; margin-top: .5rem; }
        .section-head p { color: var(--muted-strong); margin-top: .8rem; }

        /* Features grid */
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.1rem; }

        .feature {
            background: #fff;
            border: 1px solid #cbd5e1;
            border-radius: .9rem;
            padding: 1.35rem;
            box-shadow: 0 8px 24px -18px rgb(15 23 42 / .4);
            transition: transform 200ms ease, border-color 200ms ease, box-shadow 200ms ease;
        }

        .feature:hover {
            transform: translateY(-3px);
            border-color: var(--green-600);
            box-shadow: 0 18px 40px -30px rgb(21 128 61 / .5);
        }

        .feature .icon {
            width: 2.6rem;
            height: 2.6rem;
            border-radius: .75rem;
            display: grid;
            place-items: center;
            background: var(--green-600);
            color: #fff;
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: .9rem;
        }

        .feature h3 { font-size: 1.02rem; font-weight: 700; color: var(--ink-strong); }
        .feature p { font-size: .88rem; color: var(--muted-strong); margin-top: .35rem; }

        /* Roles */
        .roles {
            background: linear-gradient(180deg, var(--green-900), var(--green-950));
            color: #fff;
            border-top: 1px solid var(--green-800);
        }

        .roles .section-head h2 { color: #fff; }
        .roles .section-head p { color: rgb(226 232 240 / .9); }
        .roles .section-head .kicker { color: #4ade80; }

        .roles-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.1rem; }

        .role {
            border: 1px solid rgb(255 255 255 / .25);
            border-radius: .9rem;
            padding: 1.4rem;
            background: rgb(255 255 255 / .1);
            backdrop-filter: blur(4px);
            transition: transform 200ms ease, border-color 200ms ease, background 200ms ease;
        }

        .role:hover { transform: translateY(-3px); border-color: #4ade80; background: rgb(255 255 255 / .16); }

        .role .tag {
            display: inline-block;
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #052e16;
            background: #4ade80;
            border-radius: 999px;
            padding: .2rem .65rem;
            margin-bottom: .8rem;
        }

        .role h3 { font-size: 1.05rem; font-weight: 700; color: #fff; }
        .role p { font-size: .87rem; color: rgb(226 232 240 / .92); margin-top: .4rem; }

        /* CTA */
        .cta { text-align: center; }
        .cta .inner {
            background:
                radial-gradient(circle at 50% 0%, rgb(22 163 74 / .12), transparent 20rem),
                #fff;
            border: 1px solid #cbd5e1;
            border-radius: 1.2rem;
            padding: 3.5rem 2rem;
            box-shadow: 0 12px 32px -24px rgb(15 23 42 / .4);
        }
        .cta h2 { font-size: clamp(1.7rem, 3.5vw, 2.3rem); letter-spacing: -.02em; font-weight: 800; }
        .cta p { color: var(--muted-strong); max-width: 30rem; margin: .8rem auto 1.8rem; }

        /* Footer */
        footer { border-top: 1px solid #cbd5e1; background: #fff; padding-block: 2rem; }
        footer .container { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; }
        footer .brand img { width: 1.9rem; height: 1.9rem; }
        footer .muted { color: var(--muted-strong); font-size: .85rem; }

        @media (max-width: 860px) {
            .hero .container { grid-template-columns: 1fr; padding-block: 3.5rem; text-align: center; }
            .hero p.lead { margin-inline: auto; }
            .hero-cta { justify-content: center; }
            .hero-meta { justify-content: center; }
            .hero-visual .logo-wrap { width: min(16rem, 100%); }
        }

        @media (max-width: 480px) {
            .nav .container { gap: .5rem; }
            .btn { padding: .5rem .9rem; font-size: .85rem; }
        }
    </style>
</head>
<body>
    <nav class="nav">
        <div class="container">
            <a href="{{ url('/') }}" class="brand">
                <img src="/images/autofix-logo.svg" alt="Logo de AUTOFIX IA">
                <span>AUTOFIX <span class="accent">IA</span></span>
            </a>
            <div class="nav-links">
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-primary">Ir al panel</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline">Iniciar sesión</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="btn btn-primary">Registrarse</a>
                    @endif
                @endauth
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="container">
            <div>
                <span class="hero-badge"><span class="dot"></span> Gestión integral para tu taller automotriz</span>
                <h1>Administra tu taller con ayuda de <span class="accent">inteligencia artificial</span></h1>
                <p class="lead">
                    AUTOFIX IA centraliza clientes, vehículos, citas, órdenes de trabajo, diagnóstico técnico
                    asistido, inventario, facturación, pagos, historial, auditoría y reportes en un solo lugar.
                </p>
                <div class="hero-cta">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg">Ir al panel</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Iniciar sesión</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-outline btn-lg">Crear cuenta</a>
                        @endif
                    @endauth
                </div>
                <div class="hero-meta">
                    <div><strong>4 roles</strong><span>Administrador · Mecánico</span></div>
                    <div><strong>16 módulos</strong><span>Operación completa</span></div>
                    <div><strong>IA</strong><span>Diagnóstico asistido</span></div>
                </div>
            </div>
            <div class="hero-visual">
                <div class="logo-wrap">
                    <img src="/images/autofix-logo.svg" alt="Logo de AUTOFIX IA">
                </div>
            </div>
        </div>
    </header>

    <section id="modulos">
        <div class="container">
            <div class="section-head">
                <span class="kicker">Módulos</span>
                <h2>Todo lo que tu taller necesita</h2>
                <p>Un sistema único que conecta cada área de tu operación, desde la recepción del cliente hasta la facturación final.</p>
            </div>
            <div class="features">
                <article class="feature">
                    <div class="icon">C</div>
                    <h3>Clientes y vehículos</h3>
                    <p>Gestiona la información de tus clientes y el historial de sus vehículos.</p>
                </article>
                <article class="feature">
                    <div class="icon">C</div>
                    <h3>Citas</h3>
                    <p>Programa, confirma y recuerda las citas de servicio automáticamente.</p>
                </article>
                <article class="feature">
                    <div class="icon">O</div>
                    <h3>Órdenes de trabajo</h3>
                    <p>Controla servicios, repuestos autorizados y avances por mecánico.</p>
                </article>
                <article class="feature">
                    <div class="icon">IA</div>
                    <h3>Diagnóstico asistido</h3>
                    <p>Apoyo de IA para diagnosticar fallas con la información del vehículo.</p>
                </article>
                <article class="feature">
                    <div class="icon">I</div>
                    <h3>Inventario</h3>
                    <p>Controla existencias, movimientos y disponibilidad de repuestos.</p>
                </article>
                <article class="feature">
                    <div class="icon">F</div>
                    <h3>Facturación y pagos</h3>
                    <p>Facturas internas y comprobantes en PDF con envío por correo.</p>
                </article>
                <article class="feature">
                    <div class="icon">H</div>
                    <h3>Historial vehicular</h3>
                    <p>Registro no destructivo del historial de servicios de cada vehículo.</p>
                </article>
                <article class="feature">
                    <div class="icon">A</div>
                    <h3>Auditoría y reportes</h3>
                    <p>Auditoría de operaciones sensibles y reportes de la operación.</p>
                </article>
            </div>
        </div>
    </section>

    <section id="roles" class="roles">
        <div class="container">
            <div class="section-head">
                <span class="kicker">Roles</span>
                <h2>Pensado para cada perfil del taller</h2>
                <p>Permisos por rol para que cada persona vea exactamente lo que necesita.</p>
            </div>
            <div class="roles-grid">
                <article class="role">
                    <span class="tag">Administrador</span>
                    <h3>Control total</h3>
                    <p>Configuración, usuarios, auditoría y acceso operativo completo.</p>
                </article>
                <article class="role">
                    <span class="tag">Recepcionista</span>
                    <h3>Operación de frente</h3>
                    <p>Clientes, vehículos, citas, órdenes, facturas, pagos y envíos de documentos.</p>
                </article>
                <article class="role">
                    <span class="tag">Mecánico</span>
                    <h3>Taller</h3>
                    <p>Órdenes asignadas, diagnósticos, avances, servicios y repuestos autorizados.</p>
                </article>
                <article class="role">
                    <span class="tag">Cliente</span>
                    <h3>Autoservicio</h3>
                    <p>Registro público, vehículos propios, citas, órdenes e historial propio.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="cta">
        <div class="container">
            <div class="inner">
                <h2>¿Listo para digitalizar tu taller?</h2>
                <p>Ingresa al sistema y empieza a gestionar tu operación de forma centralizada, segura y con apoyo de IA.</p>
                @auth
                    <a href="{{ url('/dashboard') }}" class="btn btn-primary btn-lg">Ir al panel</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Iniciar sesión</a>
                @endauth
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <a href="{{ url('/') }}" class="brand">
                <img src="/images/autofix-logo.svg" alt="Logo de AUTOFIX IA">
                <span>AUTOFIX <span class="accent">IA</span></span>
            </a>
            <span class="muted">Sistema de gestión integral para talleres automotrices.</span>
        </div>
    </footer>
</body>
</html>
