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

const TYPE_OFFSETS = {
    group: -30,
    user: -18,
    followup: -6,
    task: 6,
    solution: 18,
    validation: 30,
};

// Half the gap between the out arrow and the in arrow at each shared card (px).
const EXIT_OFF = 5;

const CHAIN_STYLE = [
    { key: 'group',      color: '#3a7bbf', marker: 'group' },
    { key: 'user',       color: '#e05555', marker: 'user' },
    { key: 'followup',   color: '#0891b2', marker: 'followup' },
    { key: 'task',       color: '#b45309', marker: 'task' },
    { key: 'solution',   color: '#16a34a', marker: 'solution' },
    { key: 'validation', color: '#7c3aed', marker: 'validation' },
];

const CARD_TYPES = ['group', 'user', 'followup', 'task', 'solution', 'validation'];

function initSwimlane(wrap) {
    const uid = wrap.dataset.uid;
    let chains;
    try {
        chains = JSON.parse(wrap.dataset.chains || '{}');
    } catch (e) {
        return;
    }

    function cardRect(id) {
        const el = document.getElementById(id);
        if (!el) {
            return null;
        }
        const wr = wrap.getBoundingClientRect();
        const r = el.getBoundingClientRect();
        return {
            left:   r.left  - wr.left,
            right:  r.right - wr.left,
            top:    r.top   - wr.top,
            bottom: r.bottom - wr.top,
            midX:   r.left  - wr.left + r.width / 2,
            midY:   r.top   - wr.top + r.height / 2,
        };
    }

    function laneTop(cardId) {
        const el = document.getElementById(cardId);
        if (!el) {
            return null;
        }
        const lane = el.closest('.tt-lane');
        if (!lane) {
            return null;
        }
        return lane.getBoundingClientRect().top - wrap.getBoundingClientRect().top;
    }

    // off     = per-type lateral offset (keeps parallel chains apart across the swimlane)
    // exitOff = per-segment exit side-step: separates the in and out arrows at each card
    function addArrow(svg, fromId, toId, color, markerSuffix, off, exitOff) {
        const f = cardRect(fromId);
        const t = cardRect(toId);
        if (!f || !t) {
            return;
        }

        const fEl = document.getElementById(fromId);
        const tEl = document.getElementById(toId);
        const fLane = fEl ? fEl.closest('.tt-lane') : null;
        const tLane = tEl ? tEl.closest('.tt-lane') : null;
        const sameLane = (fLane && tLane && fLane === tLane);
        const fLaneTop = laneTop(fromId);
        const tLaneTop = laneTop(toId);
        const backward = (!sameLane && tLaneTop !== null && fLaneTop !== null && tLaneTop < fLaneTop);

        let x1, y1, x2, y2, cx, cy, gutter, pathD;

        if (sameLane) {
            const rowGap = t.midY - f.midY;
            if (Math.abs(rowGap) < 25) {
                // Same visual row: exit right, enter left
                x1 = f.right; y1 = f.midY + off + exitOff;
                x2 = t.left;  y2 = t.midY + off - exitOff;
                cx = (x1 + x2) / 2;
                pathD = 'M' + x1 + ',' + y1 + ' C' + cx + ',' + y1 + ' ' + cx + ',' + y2 + ' ' + x2 + ',' + y2;
            } else if (rowGap > 0) {
                // Wrapped to next row (forward): exit right → gutter → enter left
                x1 = f.right; y1 = f.midY + off;
                x2 = t.left;  y2 = t.midY + off;
                gutter = Math.max(f.right, t.right) + 18 + Math.abs(off);
                pathD = 'M' + x1 + ',' + y1
                      + ' C' + gutter + ',' + y1
                      + ' '  + gutter + ',' + y2
                      + ' '  + x2 + ',' + y2;
            } else {
                // Wrapped backward (going up): exit left → gutter-left → enter right
                x1 = f.left;  y1 = f.midY + off;
                x2 = t.right; y2 = t.midY + off;
                gutter = Math.min(f.left, t.left) - 18 - Math.abs(off);
                pathD = 'M' + x1 + ',' + y1
                      + ' C' + gutter + ',' + y1
                      + ' '  + gutter + ',' + y2
                      + ' '  + x2 + ',' + y2;
            }
        } else if (!backward) {
            // Forward: exit bottom, enter top — offset horizontally
            x1 = f.midX + off + exitOff; y1 = f.bottom;
            x2 = t.midX + off - exitOff; y2 = t.top;
            cy = (y1 + y2) / 2;
            pathD = 'M' + x1 + ',' + y1 + ' C' + x1 + ',' + cy + ' ' + x2 + ',' + cy + ' ' + x2 + ',' + y2;
        } else {
            // Backward: exit top, enter bottom — offset horizontally
            x1 = f.midX + off - exitOff; y1 = f.top;
            x2 = t.midX + off + exitOff; y2 = t.bottom;
            cy = (y1 + y2) / 2;
            pathD = 'M' + x1 + ',' + y1 + ' C' + x1 + ',' + cy + ' ' + x2 + ',' + cy + ' ' + x2 + ',' + y2;
        }

        const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
        path.setAttribute('d', pathD);
        path.setAttribute('stroke', color);
        path.setAttribute('fill', 'none');
        path.setAttribute('stroke-width', '1.8');
        if (backward) {
            path.setAttribute('stroke-dasharray', '3,4');
            path.setAttribute('stroke-opacity', '0.6');
        } else {
            path.setAttribute('stroke-dasharray', '5,3');
        }
        path.setAttribute('marker-end', 'url(#' + uid + '-ah-' + markerSuffix + ')');
        svg.appendChild(path);
    }

    function clearArrows(svg) {
        // Remove only <path> elements, leaving <defs> intact
        svg.querySelectorAll('path').forEach(function (p) {
            svg.removeChild(p);
        });
    }

    function drawChain(svg, ids, color, marker, typeOffset) {
        for (let i = 0; i < ids.length - 1; i++) {
            addArrow(svg, ids[i], ids[i + 1], color, marker, typeOffset, EXIT_OFF);
        }
    }

    function drawArrows(filter) {
        const svg = document.getElementById(uid + '-svg');
        if (!svg) {
            return;
        }
        clearArrows(svg);
        CHAIN_STYLE.forEach(function (c) {
            if (filter === 'all' || filter === c.key) {
                drawChain(svg, chains[c.key] || [], c.color, c.marker, TYPE_OFFSETS[c.key]);
            }
        });
    }

    function applyFilter(filter) {
        CARD_TYPES.forEach(function (type) {
            const show = (filter === 'all' || filter === type);
            wrap.querySelectorAll('.tt-card-' + type).forEach(function (el) {
                el.style.display = show ? '' : 'none';
            });
        });
        drawArrows(filter);
    }

    // Toolbar buttons (rendered as siblings of the wrap, keyed by the same uid).
    const buttons = document.querySelectorAll('.tt-filter-btn[data-uid="' + uid + '"]');
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            buttons.forEach(function (b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');
            applyFilter(btn.getAttribute('data-filter'));
        });
    });

    // The swimlane may not have laid out yet when injected; retry until it has.
    let attempts = 0;
    function tryDraw() {
        if (wrap.offsetHeight > 0) {
            drawArrows('all');
        } else if (attempts++ < 20) {
            requestAnimationFrame(tryDraw);
        }
    }
    requestAnimationFrame(tryDraw);
}

function initAll() {
    document.querySelectorAll('.tt-swimlane-wrap:not([data-tt-init])').forEach(function (wrap) {
        wrap.setAttribute('data-tt-init', '1');
        initSwimlane(wrap);
    });
}

// Init any swimlane already present, then watch for asynchronously-injected tabs.
initAll();

if (document.body) {
    const observer = new MutationObserver(function () {
        initAll();
    });
    observer.observe(document.body, { childList: true, subtree: true });
}
