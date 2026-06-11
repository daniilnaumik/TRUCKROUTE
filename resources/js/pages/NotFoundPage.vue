<template>
    <div style="min-height:60vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:40px;">
        <div>
            <p style="font-family:var(--font-m);font-size:64px;color:var(--accent);line-height:1;">404</p>
            <h1 style="margin-top:16px;">Страница не найдена</h1>
            <p class="lead" style="margin-top:12px;">Такой страницы не существует или она была перемещена.</p>
            <div class="actions" style="margin-top:28px;justify-content:center;">
                <RouterLink :to="{ name: 'home' }" class="btn">На главную</RouterLink>
            </div>
        </div>
    </div>
</template>
