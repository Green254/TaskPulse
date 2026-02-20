<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TaskPulse API Tester</title>
    <style>
        :root {
            --bg: #f4f6ff;
            --bg2: #ecfff6;
            --card: #ffffff;
            --ink: #152033;
            --muted: #5b6a84;
            --brand: #0f62fe;
            --ok: #0f8f57;
            --warn: #bb4d00;
            --border: #d9e0f0;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", "Trebuchet MS", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at 15% 10%, #d9e8ff 0%, transparent 30%),
                radial-gradient(circle at 85% 20%, #d4ffe8 0%, transparent 35%),
                linear-gradient(130deg, var(--bg), var(--bg2));
            padding: 24px;
        }
        .wrap {
            max-width: 980px;
            margin: 0 auto;
            display: grid;
            gap: 14px;
        }
        .head {
            background: linear-gradient(120deg, #0f62fe, #0b8bd9);
            color: #fff;
            border-radius: 14px;
            padding: 18px 20px;
        }
        .head h1 {
            margin: 0;
            font-size: 24px;
            letter-spacing: .2px;
        }
        .head p {
            margin: 6px 0 0;
            opacity: .95;
            font-size: 14px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
        }
        .card h2 {
            margin: 0 0 12px;
            font-size: 16px;
        }
        .muted {
            color: var(--muted);
            font-size: 13px;
        }
        .stack {
            display: grid;
            gap: 10px;
        }
        label {
            display: grid;
            gap: 5px;
            font-size: 13px;
            color: var(--muted);
        }
        input {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 10px 11px;
            font-size: 14px;
            color: var(--ink);
            background: #fff;
        }
        .row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        button {
            border: 0;
            border-radius: 8px;
            background: var(--brand);
            color: #fff;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }
        button.alt { background: #3f4f6b; }
        button.warn { background: var(--warn); }
        .pill {
            display: inline-block;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #eef4ff;
            color: #234e99;
            border: 1px solid #d2e1ff;
            margin-top: 8px;
        }
        pre {
            margin: 0;
            max-height: 360px;
            overflow: auto;
            background: #0f1727;
            color: #d6e6ff;
            border-radius: 10px;
            padding: 12px;
            font-size: 12px;
            line-height: 1.45;
        }
        @media (max-width: 860px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="head">
            <h1>TaskPulse Backend Tester</h1>
            <p>Login, fetch profile, list tasks, and create tasks from this page.</p>
            <div class="pill">API Base: <span id="apiBase"></span></div>
        </div>

        <div class="grid">
            <section class="card">
                <h2>Auth</h2>
                <div class="stack">
                    <label>Email
                        <input id="email" type="email" value="demo@taskpulse.test">
                    </label>
                    <label>Password
                        <input id="password" type="password" value="password123">
                    </label>
                    <div class="row">
                        <button id="btnLogin">Login</button>
                        <button id="btnMe" class="alt">Get /api/me</button>
                        <button id="btnLogout" class="warn">Logout</button>
                    </div>
                    <div class="muted">Token status: <strong id="tokenState">not set</strong></div>
                </div>
            </section>

            <section class="card">
                <h2>Tasks</h2>
                <div class="stack">
                    <label>Project ID
                        <input id="projectId" type="number" value="13">
                    </label>
                    <label>Task Title
                        <input id="taskTitle" type="text" value="Website smoke test task">
                    </label>
                    <div class="row">
                        <button id="btnList">List /api/tasks</button>
                        <button id="btnCreate">Create Task</button>
                    </div>
                    <div class="muted">Demo project id is pre-filled for demo user.</div>
                </div>
            </section>
        </div>

        <section class="card">
            <h2>Response</h2>
            <pre id="out">Ready.</pre>
        </section>
    </div>

    <script>
        const apiBase = `${window.location.origin}/api`;
        document.getElementById("apiBase").textContent = apiBase;

        let token = "";
        const out = document.getElementById("out");
        const tokenState = document.getElementById("tokenState");

        function show(data) {
            out.textContent = typeof data === "string" ? data : JSON.stringify(data, null, 2);
        }

        function setToken(value) {
            token = value || "";
            tokenState.textContent = token ? "set" : "not set";
        }

        async function call(path, options = {}) {
            const headers = {
                "Accept": "application/json",
                ...(options.body ? { "Content-Type": "application/json" } : {}),
                ...(token ? { "Authorization": `Bearer ${token}` } : {}),
                ...(options.headers || {})
            };

            const res = await fetch(`${apiBase}${path}`, {
                method: options.method || "GET",
                headers,
                body: options.body ? JSON.stringify(options.body) : undefined
            });

            const text = await res.text();
            let parsed;
            try { parsed = JSON.parse(text); } catch { parsed = text; }

            return { status: res.status, data: parsed };
        }

        document.getElementById("btnLogin").addEventListener("click", async () => {
            const email = document.getElementById("email").value.trim();
            const password = document.getElementById("password").value;
            const response = await call("/login", { method: "POST", body: { email, password } });
            if (response.status === 200 && response.data.token) {
                setToken(response.data.token);
            }
            show(response);
        });

        document.getElementById("btnMe").addEventListener("click", async () => {
            show(await call("/me"));
        });

        document.getElementById("btnLogout").addEventListener("click", async () => {
            const response = await call("/logout", { method: "POST" });
            setToken("");
            show(response);
        });

        document.getElementById("btnList").addEventListener("click", async () => {
            show(await call("/tasks"));
        });

        document.getElementById("btnCreate").addEventListener("click", async () => {
            const projectId = Number(document.getElementById("projectId").value);
            const title = document.getElementById("taskTitle").value.trim();
            show(await call("/tasks", {
                method: "POST",
                body: {
                    project_id: projectId,
                    title: title || "Untitled task",
                    description: "Created from /api-tester",
                    status: "pending"
                }
            }));
        });
    </script>
</body>
</html>
