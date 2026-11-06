/**
 * EventFlow Native Canvas Charting Engine
 * Lightweight, high-performance HTML5 canvas chart visualizer
 */

(function (window) {
	'use strict';

	var EventFlowChart = {
		/**
		 * Draw Smooth Area / Line Chart
		 */
		drawLineChart: function (canvas, labels, data, color) {
			if (!canvas || !canvas.getContext) return;
			var ctx = canvas.getContext('2d');
			var dpr = window.devicePixelRatio || 1;
			var rect = canvas.getBoundingClientRect();
			var width = rect.width > 0 ? rect.width : (canvas.offsetWidth || 500);
			var height = parseInt(canvas.getAttribute('height') || 230, 10);

			canvas.width = width * dpr;
			canvas.height = height * dpr;
			ctx.scale(dpr, dpr);

			var padding = { top: 20, right: 20, bottom: 30, left: 35 };

			var plotW = width - padding.left - padding.right;
			var plotH = height - padding.top - padding.bottom;

			ctx.clearRect(0, 0, width, height);

			var maxVal = Math.max.apply(null, data.concat([5]));
			maxVal = Math.ceil(maxVal * 1.15);

			// Draw horizontal grid lines
			var gridSteps = 4;
			ctx.lineWidth = 1;
			ctx.strokeStyle = '#F1F5F9';
			ctx.fillStyle = '#94A3B8';
			ctx.font = '11px -apple-system, sans-serif';
			ctx.textAlign = 'right';

			for (var i = 0; i <= gridSteps; i++) {
				var yVal = Math.round((maxVal / gridSteps) * i);
				var y = padding.top + plotH - (plotH * (yVal / maxVal));
				ctx.beginPath();
				ctx.moveTo(padding.left, y);
				ctx.lineTo(width - padding.right, y);
				ctx.stroke();
				ctx.fillText(yVal, padding.left - 8, y + 4);
			}

			if (!data.length) return;

			// Calculate point coordinates
			var points = [];
			var stepX = plotW / Math.max(1, (data.length - 1));

			for (var j = 0; j < data.length; j++) {
				var px = padding.left + (j * stepX);
				var py = padding.top + plotH - (plotH * (data[j] / maxVal));
				points.push({ x: px, y: py, val: data[j], label: labels[j] });
			}

			// Draw Gradient Area
			var strokeColor = color || '#4F46E5';
			var grad = ctx.createLinearGradient(0, padding.top, 0, height - padding.bottom);
			grad.addColorStop(0, 'rgba(79, 70, 229, 0.25)');
			grad.addColorStop(1, 'rgba(79, 70, 229, 0.00)');

			ctx.beginPath();
			ctx.moveTo(points[0].x, padding.top + plotH);
			for (var k = 0; k < points.length; k++) {
				ctx.lineTo(points[k].x, points[k].y);
			}
			ctx.lineTo(points[points.length - 1].x, padding.top + plotH);
			ctx.closePath();
			ctx.fillStyle = grad;
			ctx.fill();

			// Draw Line Path
			ctx.beginPath();
			ctx.moveTo(points[0].x, points[0].y);
			for (var l = 1; l < points.length; l++) {
				ctx.lineTo(points[l].x, points[l].y);
			}
			ctx.strokeStyle = strokeColor;
			ctx.lineWidth = 2.5;
			ctx.stroke();

			// Draw points and X labels
			ctx.textAlign = 'center';
			for (var p = 0; p < points.length; p++) {
				ctx.beginPath();
				ctx.arc(points[p].x, points[p].y, 3.5, 0, Math.PI * 2);
				ctx.fillStyle = '#FFFFFF';
				ctx.fill();
				ctx.lineWidth = 2;
				ctx.strokeStyle = strokeColor;
				ctx.stroke();

				// X axis labels (show every few to avoid clutter)
				if (data.length <= 8 || p % 2 === 0 || p === points.length - 1) {
					ctx.fillStyle = '#64748B';
					ctx.fillText(points[p].label, points[p].x, height - 8);
				}
			}
		},

		/**
		 * Draw Horizontal Bar Chart for Activities
		 */
		drawBarChart: function (canvas, labels, data, barColor) {
			if (!canvas || !canvas.getContext) return;
			var ctx = canvas.getContext('2d');
			var dpr = window.devicePixelRatio || 1;
			var rect = canvas.getBoundingClientRect();
			var width = rect.width > 0 ? rect.width : (canvas.offsetWidth || 500);
			var height = parseInt(canvas.getAttribute('height') || 230, 10);

			canvas.width = width * dpr;
			canvas.height = height * dpr;
			ctx.scale(dpr, dpr);

			var padding = { top: 10, right: 40, bottom: 20, left: 140 };

			ctx.clearRect(0, 0, width, height);

			if (!data.length) {
				ctx.fillStyle = '#94A3B8';
				ctx.font = '13px sans-serif';
				ctx.textAlign = 'center';
				ctx.fillText('No data available', width / 2, height / 2);
				return;
			}

			var maxVal = Math.max.apply(null, data.concat([5]));
			var plotW = width - padding.left - padding.right;
			var barHeight = Math.min(22, Math.floor((height - padding.top - padding.bottom) / data.length) - 8);

			var color = barColor || '#06B6D4';

			for (var i = 0; i < data.length; i++) {
				var y = padding.top + (i * (barHeight + 8));
				var barW = maxVal > 0 ? (data[i] / maxVal) * plotW : 0;

				// Label
				ctx.fillStyle = '#334155';
				ctx.font = '12px -apple-system, sans-serif';
				ctx.textAlign = 'right';
				var truncatedLabel = labels[i].length > 18 ? labels[i].substring(0, 16) + '...' : labels[i];
				ctx.fillText(truncatedLabel, padding.left - 10, y + (barHeight / 2) + 4);

				// Background Track
				ctx.fillStyle = '#F1F5F9';
				ctx.beginPath();
				if (typeof ctx.roundRect === 'function') {
					ctx.roundRect(padding.left, y, plotW, barHeight, 4);
				} else {
					ctx.rect(padding.left, y, plotW, barHeight);
				}
				ctx.fill();

				// Colored Bar
				if (barW > 0) {
					ctx.fillStyle = color;
					ctx.beginPath();
					if (typeof ctx.roundRect === 'function') {
						ctx.roundRect(padding.left, y, Math.max(8, barW), barHeight, 4);
					} else {
						ctx.rect(padding.left, y, Math.max(8, barW), barHeight);
					}
					ctx.fill();
				}

				// Count value on right
				ctx.fillStyle = '#0F172A';
				ctx.font = 'bold 11px sans-serif';
				ctx.textAlign = 'left';
				ctx.fillText(data[i], padding.left + barW + 8, y + (barHeight / 2) + 4);
			}
		},

		/**
		 * Draw Doughnut Chart for Application Status
		 */
		drawDoughnutChart: function (canvas, labels, data) {
			if (!canvas || !canvas.getContext) return;
			var ctx = canvas.getContext('2d');
			var dpr = window.devicePixelRatio || 1;
			var rect = canvas.getBoundingClientRect();

			canvas.width = rect.width * dpr;
			canvas.height = (canvas.getAttribute('height') || 200) * dpr;
			ctx.scale(dpr, dpr);

			var width = rect.width;
			var height = parseInt(canvas.getAttribute('height') || 200, 10);

			ctx.clearRect(0, 0, width, height);

			var total = 0;
			for (var i = 0; i < data.length; i++) total += data[i];

			var centerX = width * 0.35;
			var centerY = height / 2;
			var radius = Math.min(centerX, centerY) - 15;
			var innerRadius = radius * 0.65;

			var colors = ['#3B82F6', '#F59E0B', '#10B981', '#EF4444', '#8B5CF6'];

			if (total === 0) {
				ctx.fillStyle = '#E2E8F0';
				ctx.beginPath();
				ctx.arc(centerX, centerY, radius, 0, Math.PI * 2);
				ctx.arc(centerX, centerY, innerRadius, Math.PI * 2, 0, true);
				ctx.fill();

				ctx.fillStyle = '#94A3B8';
				ctx.font = '12px sans-serif';
				ctx.textAlign = 'center';
				ctx.fillText('0 Apps', centerX, centerY + 4);
				return;
			}

			var currentAngle = -0.5 * Math.PI;

			for (var j = 0; j < data.length; j++) {
				if (data[j] <= 0) continue;
				var sliceAngle = (data[j] / total) * 2 * Math.PI;

				ctx.fillStyle = colors[j % colors.length];
				ctx.beginPath();
				ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
				ctx.arc(centerX, centerY, innerRadius, currentAngle + sliceAngle, currentAngle, true);
				ctx.closePath();
				ctx.fill();

				currentAngle += sliceAngle;
			}

			// Center text
			ctx.fillStyle = '#0F172A';
			ctx.font = 'bold 16px sans-serif';
			ctx.textAlign = 'center';
			ctx.fillText(total, centerX, centerY + 2);
			ctx.font = '10px sans-serif';
			ctx.fillStyle = '#64748B';
			ctx.fillText('TOTAL', centerX, centerY + 14);

			// Draw Legend on right side
			var legendX = width * 0.65;
			var legendY = 25;
			ctx.textAlign = 'left';

			for (var k = 0; k < labels.length; k++) {
				ctx.fillStyle = colors[k % colors.length];
				ctx.beginPath();
				ctx.arc(legendX, legendY + (k * 24), 5, 0, Math.PI * 2);
				ctx.fill();

				ctx.fillStyle = '#334155';
				ctx.font = '12px -apple-system, sans-serif';
				ctx.fillText(labels[k] + ' (' + data[k] + ')', legendX + 12, legendY + (k * 24) + 4);
			}
		},

		/**
		 * Draw Vertical Column / Bar Chart
		 */
		drawVerticalBarChart: function (canvas, labels, data, barColor) {
			if (!canvas || !canvas.getContext) return;
			var ctx = canvas.getContext('2d');
			var dpr = window.devicePixelRatio || 1;
			var rect = canvas.getBoundingClientRect();
			var width = rect.width > 0 ? rect.width : (canvas.offsetWidth || 500);
			var height = parseInt(canvas.getAttribute('height') || 220, 10);

			canvas.width = width * dpr;
			canvas.height = height * dpr;
			ctx.scale(dpr, dpr);

			var padding = { top: 20, right: 20, bottom: 30, left: 35 };
			var plotW = width - padding.left - padding.right;
			var plotH = height - padding.top - padding.bottom;

			ctx.clearRect(0, 0, width, height);

			if (!data.length) {
				ctx.fillStyle = '#94A3B8';
				ctx.font = '13px sans-serif';
				ctx.textAlign = 'center';
				ctx.fillText('No data available', width / 2, height / 2);
				return;
			}

			var maxVal = Math.max.apply(null, data.concat([5]));
			maxVal = Math.ceil(maxVal * 1.15);

			// Grid
			var gridSteps = 4;
			ctx.lineWidth = 1;
			ctx.strokeStyle = '#F1F5F9';
			ctx.fillStyle = '#94A3B8';
			ctx.font = '11px -apple-system, sans-serif';
			ctx.textAlign = 'right';

			for (var i = 0; i <= gridSteps; i++) {
				var yVal = Math.round((maxVal / gridSteps) * i);
				var y = padding.top + plotH - (plotH * (yVal / maxVal));
				ctx.beginPath();
				ctx.moveTo(padding.left, y);
				ctx.lineTo(width - padding.right, y);
				ctx.stroke();
				ctx.fillText(yVal, padding.left - 8, y + 4);
			}

			var colWidth = Math.max(12, Math.min(36, Math.floor(plotW / data.length) - 10));
			var stepX = plotW / data.length;
			var color = barColor || '#0D9488';

			for (var j = 0; j < data.length; j++) {
				var x = padding.left + (j * stepX) + (stepX - colWidth) / 2;
				var barH = maxVal > 0 ? (data[j] / maxVal) * plotH : 0;
				var barY = padding.top + plotH - barH;

				// Bar
				ctx.fillStyle = color;
				ctx.beginPath();
				if (typeof ctx.roundRect === 'function') {
					ctx.roundRect(x, barY, colWidth, Math.max(4, barH), [4, 4, 0, 0]);
				} else {
					ctx.rect(x, barY, colWidth, Math.max(4, barH));
				}
				ctx.fill();

				// Value on top of bar
				if (data[j] > 0) {
					ctx.fillStyle = '#0F172A';
					ctx.font = 'bold 10px sans-serif';
					ctx.textAlign = 'center';
					ctx.fillText(data[j], x + (colWidth / 2), barY - 4);
				}

				// X Axis label
				ctx.fillStyle = '#64748B';
				ctx.font = '11px -apple-system, sans-serif';
				ctx.textAlign = 'center';
				var lbl = labels[j] || '';
				if (lbl.length > 8) lbl = lbl.substring(0, 7) + '..';
				ctx.fillText(lbl, x + (colWidth / 2), height - 8);
			}
		}
	};

	window.EventFlowChart = EventFlowChart;

})(window);

