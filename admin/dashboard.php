<?php
/**
 * 仪表盘页面
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/layout.php';
require_auth();

admin_header('仪表盘', 'dashboard');
?>

<div class="page-header">
    <h1>仪表盘</h1>
    <span style="color:var(--text-secondary);font-size:0.9em;">
        <i class="fas fa-calendar"></i> <?= date('Y年m月d日') ?>
    </span>
</div>

<div class="stats-grid" id="statsGrid">
    <div class="stat-card">
        <div class="label">今日访问</div>
        <div class="value" id="todayVisits">-</div>
        <div class="change" id="visitsChange"></div>
    </div>
    <div class="stat-card">
        <div class="label">今日下载</div>
        <div class="value" id="todayDownloads">-</div>
    </div>
    <div class="stat-card">
        <div class="label">累计访问</div>
        <div class="value" id="totalVisits">-</div>
    </div>
    <div class="stat-card">
        <div class="label">累计下载</div>
        <div class="value" id="totalDownloads">-</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:24px;">
    <div class="card">
        <h3>7天趋势</h3>
        <canvas id="trendChart" height="200"></canvas>
    </div>
    <div class="card">
        <h3>今日来源 TOP 10</h3>
        <div id="refererList" style="font-size:0.9em;"></div>
    </div>
</div>

<div class="card">
    <h3>今日下载明细</h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>应用</th><th>类型</th><th>次数</th></tr>
            </thead>
            <tbody id="dlDetailBody">
                <tr><td colspan="3" style="text-align:center;color:var(--text-secondary);">加载中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(async () => {
    try {
        const data = await API.get('/admin/api/dashboard.php');

        // 统计卡片
        document.getElementById('todayVisits').textContent = data.today_visits.toLocaleString();
        document.getElementById('todayDownloads').textContent = data.today_downloads_total.toLocaleString();
        document.getElementById('totalVisits').textContent = data.total_visits.toLocaleString();
        document.getElementById('totalDownloads').textContent = data.total_downloads.toLocaleString();

        // 访问变化
        const change = data.today_visits - data.yesterday_visits;
        const changeEl = document.getElementById('visitsChange');
        if (change > 0) {
            changeEl.className = 'change up';
            changeEl.textContent = `↑ 较昨日 +${change}`;
        } else if (change < 0) {
            changeEl.className = 'change down';
            changeEl.textContent = `↓ 较昨日 ${change}`;
        } else {
            changeEl.textContent = '与昨日持平';
        }

        // 7天趋势图
        const ctx = document.getElementById('trendChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.trend_7day.dates.map(d => d.slice(5)),
                datasets: [
                    {
                        label: '访问量',
                        data: data.trend_7day.visits,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102,126,234,0.1)',
                        fill: true, tension: 0.4,
                    },
                    {
                        label: '下载量',
                        data: data.trend_7day.downloads,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16,185,129,0.1)',
                        fill: true, tension: 0.4,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 } },
                },
            },
        });

        // 来源列表
        const refDiv = document.getElementById('refererList');
        if (data.top_referers.length === 0) {
            refDiv.innerHTML = '<div class="empty-state"><i class="fas fa-globe"></i><p>暂无来源数据</p></div>';
        } else {
            refDiv.innerHTML = data.top_referers.map((r, i) =>
                `<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border);">
                    <span>${i + 1}. ${r.referer}</span>
                    <span style="font-weight:600;">${r.count}</span>
                </div>`
            ).join('');
        }

        // 下载明细
        const dlBody = document.getElementById('dlDetailBody');
        const dlEntries = [];
        for (const [app, types] of Object.entries(data.today_downloads)) {
            for (const [type, count] of Object.entries(types)) {
                dlEntries.push({ app, type, count });
            }
        }
        if (dlEntries.length === 0) {
            dlBody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:var(--text-secondary);">今日暂无下载</td></tr>';
        } else {
            dlBody.innerHTML = dlEntries.map(e =>
                `<tr><td>${e.app}</td><td>${e.type}</td><td>${e.count}</td></tr>`
            ).join('');
        }
    } catch (err) {
        console.error('Dashboard load failed:', err);
    }
})();
</script>

<?php admin_footer(); ?>
