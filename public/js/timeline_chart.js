/**
 * -------------------------------------------------------------------------
 * TimelineTicket
 * Copyright (C) 2013-2026 by the TimelineTicket Development Team.
 *
 * https://github.com/pluginsGLPI/timelineticket
 * ------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of TimelineTicket project.
 *
 * TimelineTicket plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TimelineTicket plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with TimelineTicket plugin. If not, see <http://www.gnu.org/licenses/>.
 *
 * ------------------------------------------------------------------------
 *
 * @copyright Copyright (C) 2013-2025 TimelineTicket team
 * @license   AGPL License 3.0 or (at your option) any later version
 * @link      https://github.com/pluginsGLPI/timelineticket
 * @package   TimelineTicket plugin
 * @since     2013
 *            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * --------------------------------------------------------------------------
 */

/**
 * Timeline charts of the ticket tab (templates/chart.html.twig): one row per status, group or
 * technician, one bar per period, drawn with the ECharts library shipped with GLPI. The
 * library is only loaded on the pages that hold a chart.
 */

/** Colours of the bars, one per row (the Tabler palette of GLPI) */
const PALETTE = [
    '#206bc4', '#2fb344', '#f59f00', '#d63939', '#ae3ec9',
    '#0ca678', '#f76707', '#4263eb', '#74b816', '#d6336c',
];

const ROW_HEIGHT = 44;

let echarts_loading = null;

/** Charts drawn so far, disposed once their container left the page (tab reloaded) */
const charts = new Set();

/**
 * Load the ECharts library of GLPI, once
 *
 * @param {string} src
 * @returns {Promise<object>}
 */
function loadEcharts(src) {
    if (window.echarts) {
        return Promise.resolve(window.echarts);
    }
    if (echarts_loading === null) {
        echarts_loading = new Promise(function (resolve, reject) {
            const script = document.createElement('script');
            script.src = src;
            // Content security policy of GLPI: a script needs the nonce of the page
            if (window.CFG_GLPI && window.CFG_GLPI.csp_nonce) {
                script.nonce = window.CFG_GLPI.csp_nonce;
            }
            script.addEventListener('load', function () {
                resolve(window.echarts);
            });
            script.addEventListener('error', function () {
                echarts_loading = null;
                reject(new Error('timelineticket: the ECharts library could not be loaded'));
            });
            document.head.appendChild(script);
        });
    }
    return echarts_loading;
}

/**
 * A date of GLPI ("2026-11-16 08:00:00", wall-clock time of the user) as a time of the
 * browser, read as a local time so that the chart shows the dates GLPI shows
 *
 * @param {string} value
 * @returns {number}
 */
function toTime(value) {
    const [date, time = '00:00:00'] = value.split(' ');
    const [year, month, day] = date.split('-').map(Number);
    const [hour = 0, minute = 0, second = 0] = time.split(':').map(Number);
    return new Date(year, month - 1, day, hour, minute, second).getTime();
}

/**
 * Bar of one period: a rectangle on its row, from its start to its end, with its duration
 * written inside when it fits
 *
 * @param {{periods: {duration: string}[]}} data
 */
function periodRenderer(data) {
    return function (params, api) {
        return renderPeriod(params, api, data.periods[params.dataIndex].duration);
    };
}

function renderPeriod(params, api, label) {
    const from = api.coord([api.value(1), api.value(0)]);
    const to = api.coord([api.value(2), api.value(0)]);
    const height = api.size([0, 1])[1] * 0.55;
    const shape = window.echarts.graphic.clipRectByRect(
        {x: from[0], y: from[1] - height / 2, width: Math.max(to[0] - from[0], 2), height: height},
        {
            x: params.coordSys.x,
            y: params.coordSys.y,
            width: params.coordSys.width,
            height: params.coordSys.height,
        },
    );
    if (!shape) {
        return null;
    }

    const children = [{type: 'rect', transition: ['shape'], shape: shape, style: api.style()}];
    if (shape.width > label.length * 7 + 12) {
        children.push({
            type: 'text',
            style: {
                text: label,
                x: shape.x + shape.width / 2,
                y: shape.y + shape.height / 2,
                align: 'center',
                verticalAlign: 'middle',
                fill: '#fff',
                font: '600 11px sans-serif',
            },
        });
    }
    return {type: 'group', children: children};
}

/**
 * @param {object} echarts
 * @param {HTMLElement} container
 * @param {{rows: string[], periods: {row: number, begin: string, end: string, duration: string}[]}} data
 */
function draw(echarts, container, data) {
    // Colours of the page: the chart follows the palette of GLPI, dark ones included
    const style = getComputedStyle(container);
    const text = style.color;
    const line = style.getPropertyValue('--tblr-border-color').trim() || 'rgba(128, 128, 128, 0.25)';
    const surface = style.getPropertyValue('--tblr-bg-surface').trim() || '#fff';
    const date_format = new Intl.DateTimeFormat(document.documentElement.lang || undefined, {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
    const formatDate = function (value) {
        return date_format.format(value);
    };
    const encode = echarts.format.encodeHTML;

    const chart = echarts.init(container);
    chart.setOption({
        backgroundColor: 'transparent',
        animation: false,
        grid: {left: 16, right: 24, top: 12, bottom: 52, containLabel: true},
        tooltip: {
            backgroundColor: surface,
            borderColor: line,
            textStyle: {color: text},
            formatter: function (params) {
                const period = data.periods[params.dataIndex];
                return '<b>' + encode(data.rows[period.row]) + '</b><br>'
                    + encode(formatDate(params.value[1])) + ' → ' + encode(formatDate(params.value[2])) + '<br>'
                    + encode(period.duration);
            },
        },
        dataZoom: [
            {type: 'inside', filterMode: 'weakFilter'},
            {
                type: 'slider',
                height: 16,
                bottom: 12,
                filterMode: 'weakFilter',
                labelFormatter: formatDate,
                textStyle: {color: text},
                borderColor: line,
            },
        ],
        xAxis: {
            type: 'time',
            axisLabel: {color: text, formatter: formatDate, hideOverlap: true},
            axisLine: {lineStyle: {color: line}},
            splitLine: {show: true, lineStyle: {color: line}},
        },
        yAxis: {
            type: 'category',
            inverse: true,
            data: data.rows,
            axisLabel: {color: text},
            axisLine: {lineStyle: {color: line}},
            axisTick: {show: false},
        },
        series: [{
            type: 'custom',
            renderItem: periodRenderer(data),
            encode: {x: [1, 2], y: 0},
            data: data.periods.map(function (period) {
                return {
                    value: [period.row, toTime(period.begin), toTime(period.end)],
                    itemStyle: {color: PALETTE[period.row % PALETTE.length], borderRadius: 3},
                };
            }),
        }],
    });

    // The tab may be drawn hidden, or resized with the window
    const observer = new ResizeObserver(function () {
        chart.resize();
    });
    observer.observe(container);
    charts.add({chart: chart, container: container, observer: observer});
}

function initChart(container) {
    container.setAttribute('data-tt-init', '1');
    const data = JSON.parse(container.dataset.ttChart);
    container.style.height = (data.rows.length * ROW_HEIGHT + 80) + 'px';

    loadEcharts(container.dataset.ttEchartsSrc).then(function (echarts) {
        draw(echarts, container, data);
    }).catch(function (error) {
        console.error(error);
    });
}

function initAll() {
    charts.forEach(function (entry) {
        if (!entry.container.isConnected) {
            entry.observer.disconnect();
            entry.chart.dispose();
            charts.delete(entry);
        }
    });
    document.querySelectorAll('.tt-chart[data-tt-chart]:not([data-tt-init])').forEach(initChart);
}

// Charts already present, then the ones of the tabs loaded asynchronously
initAll();

if (document.body) {
    const observer = new MutationObserver(function () {
        initAll();
    });
    observer.observe(document.body, {childList: true, subtree: true});
}
