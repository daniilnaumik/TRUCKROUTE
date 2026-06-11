<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Доступ запрещён — TruckRoute</title>
    <style>
        :root { color-scheme: dark; font-family: Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #0b0d0b; color: #ecebe5; }
        main { min-height: 100vh; display: grid; place-items: center; padding: 32px; }
        section { width: min(640px, 100%); padding: 48px 0; border-block: 1px solid #292d29; }
        small { color: #d3a536; font-size: 13px; }
        h1 { margin: 14px 0 0; font: 400 clamp(38px, 7vw, 68px)/1.03 Georgia, serif; }
        p { max-width: 540px; margin: 18px 0 0; color: #999d98; line-height: 1.65; }
        a { display: inline-block; margin-top: 28px; padding: 12px 18px; border: 1px solid #424742; color: #ecebe5; text-decoration: none; }
    </style>
</head>
<body>
<main>
    <section>
        <small>403</small>
        <h1>Нет доступа к этой странице</h1>
        <p>{{ $exception->getMessage() ?: 'У вашей учётной записи недостаточно прав для просмотра этого раздела.' }}</p>
        <a href="/">На главную</a>
    </section>
</main>
</body>
</html>
