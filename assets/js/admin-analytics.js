/**
 * DropProduct Analytics Dashboard JavaScript
 *
 * @package DropProduct
 * @since 1.1.0
 */

(function ($) {
	'use strict';

	var DropProductAnalytics = {
		charts: {},
		currentData: null,
		currentRange: '30_days',

		/**
		 * Initialize the analytics dashboard
		 */
		init: function () {
			this.cache();
			this.bindEvents();
			this.loadAnalyticsData();
		},

		/**
		 * Cache DOM elements
		 */
		cache: function () {
			this.$rangeSelect = $('#dpanalytics-range-select');
			this.$exportBtn = $('#dpanalytics-export-btn');
			this.$totalSales = $('#dpa-total-sales');
			this.$totalOrders = $('#dpa-total-orders');
			this.$avgOrderValue = $('#dpa-avg-order-value');
			this.$conversionRate = $('#dpa-conversion-rate');
			this.$topProducts = $('#dpa-top-products');
			this.$countriesList = $('#dpa-countries-list');
			this.$chartSales = $('#dpa-sales-chart');
			this.$chartChannel = $('#dpa-channel-chart');
			this.$chartDevice = $('#dpa-device-chart');
		},

		/**
		 * Bind event listeners
		 */
		bindEvents: function () {
			var self = this;

			this.$rangeSelect.on('change', function () {
				self.currentRange = $(this).val();
				self.loadAnalyticsData();
			});

			this.$exportBtn.on('click', function () {
				self.exportReport();
			});
		},

		/**
		 * Load analytics data via AJAX
		 */
		loadAnalyticsData: function () {
			var self = this;

			// Show loading state
			this.showLoading();

			$.ajax({
				url: ajaxurl,
				type: 'POST',
				data: {
					action: 'dropproduct_get_analytics',
					nonce: dropproductAnalyticsData.nonce,
					range: this.currentRange,
				},
				success: function (response) {
					if (response.success && response.data) {
						self.currentData = response.data;
						self.renderDashboard();
					} else {
						self.showError('Failed to load analytics data');
					}
				},
				error: function () {
					self.showError('Error loading analytics data');
				},
			});
		},

		/**
		 * Render the entire dashboard
		 */
		renderDashboard: function () {
			if (!this.currentData) return;

			this.renderSummary();
			this.renderSalesChart();
			this.renderTopProducts();
			this.renderChannelChart();
			this.renderDeviceChart();
			this.renderCountries();
		},

		/**
		 * Render summary metrics cards
		 */
		renderSummary: function () {
			var summary = this.currentData.summary;

			this.$totalSales.text('$' + this.formatNumber(summary.total_sales));
			this.$totalOrders.text(summary.total_orders);
			this.$avgOrderValue.text('$' + this.formatNumber(summary.average_order_value));
			this.$conversionRate.text(summary.conversion_rate.toFixed(2) + '%');

			// Update growth indicators
			this.updateGrowthIndicator(
				$('#dpa-total-sales-growth'),
				summary.growth,
				summary.growth_type
			);
			this.updateGrowthIndicator(
				$('#dpa-orders-growth'),
				summary.growth,
				summary.growth_type
			);
		},

		/**
		 * Update growth indicator
		 */
		updateGrowthIndicator: function ($el, growth, type) {
			$el.find('.dpanalytics-growth-percent').text(Math.abs(growth).toFixed(1) + '%');

			if (type === 'positive') {
				$el.removeClass('negative');
			} else {
				$el.addClass('negative');
				$el.find('.dpanalytics-growth-label').text('↓');
			}
		},

		/**
		 * Render sales over time chart
		 */
		renderSalesChart: function () {
			var self = this;
			var salesData = this.currentData.sales_over_time;

			if (!salesData || salesData.length === 0) {
				this.$chartSales.parent().html(this.getEmptyState('No sales data available'));
				return;
			}

			var dates = salesData.map(function (d) {
				return self.formatDate(d.date);
			});
			var sales = salesData.map(function (d) {
				return d.sales;
			});

			var ctx = this.$chartSales[0].getContext('2d');

			// Destroy existing chart
			if (this.charts.sales) {
				this.charts.sales.destroy();
			}

			this.charts.sales = new Chart(ctx, {
				type: 'line',
				data: {
					labels: dates,
					datasets: [
						{
							label: 'Sales',
							data: sales,
							borderColor: '#6366f1',
							backgroundColor: 'rgba(99, 102, 241, 0.1)',
							borderWidth: 2,
							fill: true,
							tension: 0.4,
							pointRadius: 4,
							pointBackgroundColor: '#6366f1',
							pointBorderColor: '#fff',
							pointBorderWidth: 2,
							pointHoverRadius: 6,
							pointHoverBackgroundColor: '#4f46e5',
						},
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							display: false,
						},
						filler: {
							propagate: true,
						},
					},
					scales: {
						y: {
							beginAtZero: true,
							ticks: {
								callback: function (value) {
									return '$' + value.toLocaleString();
								},
								color: '#6b7280',
								font: {
									size: 11,
									weight: '500',
								},
							},
							grid: {
								color: '#e5e7eb',
								drawBorder: false,
							},
						},
						x: {
							grid: {
								display: false,
							},
							ticks: {
								color: '#6b7280',
								font: {
									size: 11,
									weight: '500',
								},
							},
						},
					},
				},
			});
		},

		/**
		 * Render top products list
		 */
		renderTopProducts: function () {
			var self = this;
			var topProducts = this.currentData.top_products;

			if (!topProducts || topProducts.length === 0) {
				this.$topProducts.html(this.getEmptyState('No products sold yet'));
				return;
			}

			var maxSales = Math.max.apply(
				null,
				topProducts.map(function (p) {
					return p.sales;
				})
			);

			var html = '';
			topProducts.forEach(function (product, index) {
				var barWidth = (product.sales / maxSales) * 100;
				html += `
					<div class="dpanalytics-product-row">
						<div class="dpanalytics-product-info">
							<p class="dpanalytics-product-name">${self.escapeHtml(product.product_name)}</p>
							<p class="dpanalytics-product-meta">#${product.product_id}</p>
							<div class="dpanalytics-product-bar" style="width: ${barWidth}%"></div>
						</div>
						<div class="dpanalytics-product-stats">
							<div class="dpanalytics-product-stat">
								<span class="dpanalytics-product-stat-label">Sales</span>
								<span class="dpanalytics-product-stat-value">$${self.formatNumber(product.sales)}</span>
							</div>
							<div class="dpanalytics-product-stat">
								<span class="dpanalytics-product-stat-label">Qty</span>
								<span class="dpanalytics-product-stat-value">${product.quantity}</span>
							</div>
						</div>
					</div>
				`;
			});

			this.$topProducts.html(html);
		},

		/**
		 * Render sales by channel chart
		 */
		renderChannelChart: function () {
			var channelData = this.currentData.sales_by_channel;

			if (!channelData || channelData.length === 0) {
				this.$chartChannel.parent().html(this.getEmptyState('No channel data'));
				return;
			}

			var labels = channelData.map(function (c) {
				return c.channel;
			});
			var data = channelData.map(function (c) {
				return c.percentage;
			});
			var colors = [
				'#3b82f6',
				'#10b981',
				'#f59e0b',
				'#8b5cf6',
				'#06b6d4',
				'#ef4444',
				'#ec4899',
			];

			var ctx = this.$chartChannel[0].getContext('2d');

			// Destroy existing chart
			if (this.charts.channel) {
				this.charts.channel.destroy();
			}

			this.charts.channel = new Chart(ctx, {
				type: 'doughnut',
				data: {
					labels: labels,
					datasets: [
						{
							data: data,
							backgroundColor: colors.slice(0, channelData.length),
							borderColor: '#fff',
							borderWidth: 2,
						},
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							display: false,
						},
					},
				},
			});

			this.renderChannelLegend(channelData, colors);
		},

		/**
		 * Render channel chart legend
		 */
		renderChannelLegend: function (channelData, colors) {
			var html = '';
			channelData.forEach(function (channel, index) {
				html += `
					<div class="dpanalytics-legend-item">
						<span class="dpanalytics-legend-color" style="background-color: ${colors[index]}"></span>
						<span class="dpanalytics-legend-label">${this.escapeHtml(channel.channel)}</span>
						<span class="dpanalytics-legend-value">$${this.formatNumber(channel.sales)} (${channel.percentage}%)</span>
					</div>
				`;
			}, this);

			$('#dpa-channel-legend').html(html);
		},

		/**
		 * Render sales by device chart
		 */
		renderDeviceChart: function () {
			var deviceData = this.currentData.conversion_rate;

			if (!deviceData || deviceData.length === 0) {
				this.$chartDevice.parent().html(this.getEmptyState('No device data'));
				return;
			}

			var labels = deviceData.map(function (d) {
				return d.device;
			});
			var data = deviceData.map(function (d) {
				return d.percentage;
			});
			var colors = ['#3b82f6', '#10b981', '#f59e0b'];

			var ctx = this.$chartDevice[0].getContext('2d');

			// Destroy existing chart
			if (this.charts.device) {
				this.charts.device.destroy();
			}

			this.charts.device = new Chart(ctx, {
				type: 'doughnut',
				data: {
					labels: labels,
					datasets: [
						{
							data: data,
							backgroundColor: colors,
							borderColor: '#fff',
							borderWidth: 2,
						},
					],
				},
				options: {
					responsive: true,
					maintainAspectRatio: false,
					plugins: {
						legend: {
							display: false,
						},
					},
				},
			});

			this.renderDeviceLegend(deviceData, colors);
		},

		/**
		 * Render device chart legend
		 */
		renderDeviceLegend: function (deviceData, colors) {
			var html = '';
			deviceData.forEach(function (device, index) {
				html += `
					<div class="dpanalytics-legend-item">
						<span class="dpanalytics-legend-color" style="background-color: ${colors[index]}"></span>
						<span class="dpanalytics-legend-label">${this.escapeHtml(device.device)}</span>
						<span class="dpanalytics-legend-value">$${this.formatNumber(device.sales)} (${device.percentage}%)</span>
					</div>
				`;
			}, this);

			$('#dpa-device-legend').html(html);
		},

		/**
		 * Render countries list
		 */
		renderCountries: function () {
			var self = this;
			var countries = this.currentData.sales_by_country;

			if (!countries || countries.length === 0) {
				this.$countriesList.html(this.getEmptyState('No country data'));
				return;
			}

			var totalSales = countries.reduce(function (sum, c) {
				return sum + c.sales;
			}, 0);

			var html = '';
			countries.forEach(function (country) {
				var percentage = ((country.sales / totalSales) * 100).toFixed(1);
				var barWidth = percentage;
				html += `
					<div class="dpanalytics-country-item">
						<span class="dpanalytics-country-flag">${self.getCountryFlag(country.country)}</span>
						<div class="dpanalytics-country-details">
							<p class="dpanalytics-country-name">${self.getCountryName(country.country)}</p>
							<p class="dpanalytics-country-sales">$${self.formatNumber(country.sales)}</p>
							<div class="dpanalytics-country-bar" style="width: ${barWidth}%"></div>
						</div>
					</div>
				`;
			});

			this.$countriesList.html(html);
		},

		/**
		 * Export report as PDF or CSV
		 */
		exportReport: function () {
			if (!this.currentData) return;

			var csv = this.generateCSV();
			var link = document.createElement('a');
			link.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
			link.download = 'dropproduct-analytics-' + new Date().toISOString().split('T')[0] + '.csv';
			link.click();

			this.showNotice('Report exported successfully!', 'success');
		},

		/**
		 * Generate CSV data
		 */
		generateCSV: function () {
			var summary = this.currentData.summary;
			var csv = 'DropProduct Sales Analytics Report\n';
			csv += 'Date Range,' + this.$rangeSelect.find('option:selected').text() + '\n';
			csv += 'Generated,' + new Date().toLocaleString() + '\n\n';

			csv += 'SUMMARY METRICS\n';
			csv += 'Total Sales,' + summary.total_sales + '\n';
			csv += 'Total Orders,' + summary.total_orders + '\n';
			csv += 'Average Order Value,' + summary.average_order_value + '\n';
			csv += 'Conversion Rate,' + summary.conversion_rate + '%\n\n';

			csv += 'TOP PRODUCTS\n';
			csv += 'Product,Sales,Quantity\n';
			this.currentData.top_products.forEach(function (p) {
				csv += p.product_name + ',' + p.sales + ',' + p.quantity + '\n';
			});

			csv += '\nSALES BY COUNTRY\n';
			csv += 'Country,Sales\n';
			this.currentData.sales_by_country.forEach(function (c) {
				csv += c.country + ',' + c.sales + '\n';
			});

			return csv;
		},

		/**
		 * Show loading state
		 */
		showLoading: function () {
			var html = `
				<div class="dpanalytics-loading">
					<div class="dpanalytics-spinner"></div>
					Loading analytics...
				</div>
			`;
			this.$topProducts.html(html);
			this.$countriesList.html(html);
		},

		/**
		 * Show empty state
		 */
		getEmptyState: function (message) {
			return `
				<div class="dpanalytics-empty">
					<div class="dpanalytics-empty-icon">📊</div>
					<p class="dpanalytics-empty-title">No data available</p>
					<p class="dpanalytics-empty-text">${message}</p>
				</div>
			`;
		},

		/**
		 * Show notice message
		 */
		showNotice: function (message, type) {
			type = type || 'info';
			// WordPress notice
			$('body').prepend(
				'<div class="notice notice-' +
					type +
					' is-dismissible"><p>' +
					message +
					'</p></div>'
			);
		},

		/**
		 * Show error message
		 */
		showError: function (message) {
			this.showNotice(message, 'error');
		},

		/**
		 * Format number with thousands separator
		 */
		formatNumber: function (num) {
			return parseFloat(num).toLocaleString('en-US', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			});
		},

		/**
		 * Format date for display
		 */
		formatDate: function (dateStr) {
			var date = new Date(dateStr + 'T00:00:00');
			return date.toLocaleDateString('en-US', {
				month: 'short',
				day: 'numeric',
			});
		},

		/**
		 * Get country name from code
		 */
		getCountryName: function (code) {
			var countries = {
				US: 'United States',
				GB: 'United Kingdom',
				CA: 'Canada',
				AU: 'Australia',
				DE: 'Germany',
				FR: 'France',
				IT: 'Italy',
				ES: 'Spain',
				JP: 'Japan',
				IN: 'India',
			};
			return countries[code] || code;
		},

		/**
		 * Get country flag emoji
		 */
		getCountryFlag: function (code) {
			var flags = {
				US: '🇺🇸',
				GB: '🇬🇧',
				CA: '🇨🇦',
				AU: '🇦🇺',
				DE: '🇩🇪',
				FR: '🇫🇷',
				IT: '🇮🇹',
				ES: '🇪🇸',
				JP: '🇯🇵',
				IN: '🇮🇳',
			};
			return flags[code] || '🌍';
		},

		/**
		 * Escape HTML
		 */
		escapeHtml: function (text) {
			var map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;',
			};
			return String(text).replace(/[&<>"']/g, function (s) {
				return map[s];
			});
		},
	};

	// Initialize on document ready
	$(function () {
		if (typeof dropproductAnalyticsData !== 'undefined') {
			DropProductAnalytics.init();
		}
	});
})(jQuery);
