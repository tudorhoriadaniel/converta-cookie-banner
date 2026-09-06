/**
 * Converta Cookie Banner - Admin Dashboard Charts
 */
(function () {
    'use strict';

    var s = pccStats;

    // Summary cards
    document.getElementById('pcc-total').textContent    = s.total.toLocaleString();
    document.getElementById('pcc-unique').textContent   = s.uniqueVisitors.toLocaleString();
    document.getElementById('pcc-accepted').textContent = s.acceptAll.toLocaleString();
    document.getElementById('pcc-rejected').textContent = s.rejectAll.toLocaleString();
    document.getElementById('pcc-custom').textContent   = s.savePrefs.toLocaleString();

    // Helper: percentage
    function pct(part, total) {
        if (!total) return 0;
        return Math.round((part / total) * 100);
    }

    // Consent rate bars
    function setBar(fillId, pctId, value, total) {
        var p = pct(value, total);
        document.getElementById(fillId).style.width = p + '%';
        document.getElementById(pctId).textContent  = p + '%';
    }

    setBar('pcc-rate-accept', 'pcc-rate-accept-pct', s.acceptAll + s.savePrefs, s.total);
    setBar('pcc-rate-stats',  'pcc-rate-stats-pct',  s.statsGranted, s.total);
    setBar('pcc-rate-mkt',    'pcc-rate-mkt-pct',    s.mktGranted, s.total);

    // Chart colors
    var green  = '#22c55e';
    var red    = '#ef4444';
    var amber  = '#f59e0b';
    var blue   = '#3b82f6';
    var grey   = '#d1d5db';
    var coral  = '#c0392b';

    // ---- Doughnut: Actions Breakdown ----
    new Chart(document.getElementById('pcc-actions-chart'), {
        type: 'doughnut',
        data: {
            labels: ['Accept All', 'Reject All', 'Custom'],
            datasets: [{
                data: [s.acceptAll, s.rejectAll, s.savePrefs],
                backgroundColor: [green, red, amber],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 14, usePointStyle: true, pointStyle: 'circle' } }
            }
        }
    });

    // ---- Doughnut: Statistics ----
    new Chart(document.getElementById('pcc-stats-chart'), {
        type: 'doughnut',
        data: {
            labels: ['Granted', 'Denied'],
            datasets: [{
                data: [s.statsGranted, s.statsDenied],
                backgroundColor: [blue, grey],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 14, usePointStyle: true, pointStyle: 'circle' } }
            }
        }
    });

    // ---- Doughnut: Marketing ----
    new Chart(document.getElementById('pcc-mkt-chart'), {
        type: 'doughnut',
        data: {
            labels: ['Granted', 'Denied'],
            datasets: [{
                data: [s.mktGranted, s.mktDenied],
                backgroundColor: [coral, grey],
                borderWidth: 0,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: { position: 'bottom', labels: { padding: 14, usePointStyle: true, pointStyle: 'circle' } }
            }
        }
    });

    // ---- Line: Daily Trend ----
    var days      = s.daily.map(function (d) { return d.day; });
    var accepted  = s.daily.map(function (d) { return d.accepted; });
    var rejected  = s.daily.map(function (d) { return d.rejected; });
    var custom    = s.daily.map(function (d) { return d.custom; });

    new Chart(document.getElementById('pcc-daily-chart'), {
        type: 'line',
        data: {
            labels: days,
            datasets: [
                {
                    label: 'Accept All',
                    data: accepted,
                    borderColor: green,
                    backgroundColor: green + '20',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 5
                },
                {
                    label: 'Reject All',
                    data: rejected,
                    borderColor: red,
                    backgroundColor: red + '20',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 5
                },
                {
                    label: 'Custom',
                    data: custom,
                    borderColor: amber,
                    backgroundColor: amber + '20',
                    fill: true,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { intersect: false, mode: 'index' },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, ticks: { precision: 0 } }
            },
            plugins: {
                legend: { position: 'bottom', labels: { padding: 14, usePointStyle: true, pointStyle: 'circle' } }
            }
        }
    });

})();
