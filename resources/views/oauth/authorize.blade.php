<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connect Semphony to BASIS</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: radial-gradient(circle at top, #eef2ff, #f8fafc 45%); color: #18181b; padding: 24px; }
        main { width: min(520px, 100%); border: 1px solid #e4e4e7; border-radius: 22px; background: rgba(255,255,255,.96); box-shadow: 0 24px 70px rgba(39,39,42,.14); overflow: hidden; }
        header, section, footer { padding: 24px 28px; }
        header { border-bottom: 1px solid #e4e4e7; }
        h1 { margin: 8px 0; font-size: 22px; } p { margin: 0; color: #71717a; line-height: 1.55; }
        .eyebrow { color: #7c3aed; font-size: 12px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        .account { margin-top: 18px; border-radius: 12px; background: #f4f4f5; padding: 12px 14px; font-size: 14px; }
        ul { margin: 16px 0 0; padding: 0; list-style: none; display: grid; gap: 10px; }
        li { display: flex; gap: 10px; color: #3f3f46; font-size: 14px; } li::before { content: '✓'; color: #16a34a; font-weight: 700; }
        footer { display: flex; gap: 10px; justify-content: flex-end; border-top: 1px solid #e4e4e7; background: #fafafa; }
        button { border-radius: 10px; padding: 10px 16px; border: 1px solid #d4d4d8; background: white; font: inherit; font-weight: 650; cursor: pointer; }
        .primary { color: white; background: #7c3aed; border-color: #7c3aed; }
    </style>
</head>
<body>
<main>
    <header>
        <div class="eyebrow">BASIS × Semphony</div>
        <h1>Connect your BASIS account</h1>
        <p><strong>{{ $client->name }}</strong> would like permission to use your BASIS identity and samples.</p>
        <div class="account">Signed in as <strong>{{ $user->name }}</strong><br>{{ $user->email }}</div>
    </header>
    <section>
        <p>Semphony will be able to:</p>
        <ul>
            @foreach ($scopes as $scope)
                <li>{{ $scope->description }}</li>
            @endforeach
        </ul>
    </section>
    <footer>
        <form method="POST" action="{{ route('passport.authorizations.deny') }}">
            @csrf
            @method('DELETE')
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button type="submit">Cancel</button>
        </form>
        <form method="POST" action="{{ route('passport.authorizations.approve') }}">
            @csrf
            <input type="hidden" name="auth_token" value="{{ $authToken }}">
            <button class="primary" type="submit">Connect account</button>
        </form>
    </footer>
</main>
</body>
</html>
