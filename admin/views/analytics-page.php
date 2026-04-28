<?php
/**
 * DropProduct Analytics Page Template
 *
 * @package DropProduct
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap dropproduct-wrap">
	<div class="dropproduct-analytics-header">
		<div class="dpanalytics-title-section">
			<h1 class="dpanalytics-title">📊 Sales Analytics</h1>
			<p class="dpanalytics-subtitle">Monitor your DropProduct sales performance and insights</p>
		</div>
		
		<div class="dpanalytics-controls">
			<div class="dpanalytics-date-range">
				<label for="dpanalytics-range-select">Date Range:</label>
				<select id="dpanalytics-range-select" class="dpanalytics-select">
					<option value="7_days">Last 7 days</option>
					<option value="30_days" selected>Last 30 days</option>
					<option value="90_days">Last 90 days</option>
					<option value="1_year">Last year</option>
				</select>
			</div>
			<button id="dpanalytics-export-btn" class="button button-primary dpanalytics-export-btn">
				📥 Export Report
			</button>
		</div>
	</div>

	<!-- Summary Cards -->
	<div class="dpanalytics-summary-grid">
		<div class="dpanalytics-card dpanalytics-card-primary">
			<div class="dpanalytics-card-header">
				<span class="dpanalytics-card-icon">💰</span>
				<h3>Total Sales</h3>
			</div>
			<div class="dpanalytics-card-value">
				<span class="dpanalytics-amount" id="dpa-total-sales">$0.00</span>
				<span class="dpanalytics-growth" id="dpa-total-sales-growth">
					<span class="dpanalytics-growth-label">↑</span>
					<span class="dpanalytics-growth-percent">0%</span>
				</span>
			</div>
			<p class="dpanalytics-card-meta">vs last period</p>
		</div>

		<div class="dpanalytics-card dpanalytics-card-info">
			<div class="dpanalytics-card-header">
				<span class="dpanalytics-card-icon">📦</span>
				<h3>Orders</h3>
			</div>
			<div class="dpanalytics-card-value">
				<span class="dpanalytics-amount" id="dpa-total-orders">0</span>
				<span class="dpanalytics-growth dpanalytics-growth-secondary" id="dpa-orders-growth">
					<span class="dpanalytics-growth-label">↑</span>
					<span class="dpanalytics-growth-percent">0%</span>
				</span>
			</div>
			<p class="dpanalytics-card-meta">orders received</p>
		</div>

		<div class="dpanalytics-card dpanalytics-card-success">
			<div class="dpanalytics-card-header">
				<span class="dpanalytics-card-icon">💵</span>
				<h3>Average Order Value</h3>
			</div>
			<div class="dpanalytics-card-value">
				<span class="dpanalytics-amount" id="dpa-avg-order-value">$0.00</span>
			</div>
			<p class="dpanalytics-card-meta">per order</p>
		</div>

		<div class="dpanalytics-card dpanalytics-card-warning">
			<div class="dpanalytics-card-header">
				<span class="dpanalytics-card-icon">📈</span>
				<h3>Conversion Rate</h3>
			</div>
			<div class="dpanalytics-card-value">
				<span class="dpanalytics-amount" id="dpa-conversion-rate">0%</span>
			</div>
			<p class="dpanalytics-card-meta">of store visitors</p>
		</div>
	</div>

	<div class="dpanalytics-container">
		<!-- Main Charts Section -->
		<div class="dpanalytics-main-section">
			<!-- Sales Over Time Chart -->
			<div class="dpanalytics-chart-card">
				<div class="dpanalytics-chart-header">
					<h3>Sales Over Time</h3>
					<span class="dpanalytics-chart-subtitle">Daily sales and orders</span>
				</div>
				<div class="dpanalytics-chart-wrapper">
					<canvas id="dpa-sales-chart"></canvas>
				</div>
			</div>

			<!-- Top Products Section -->
			<div class="dpanalytics-chart-card">
				<div class="dpanalytics-chart-header">
					<h3>Top Products</h3>
					<span class="dpanalytics-chart-subtitle">Highest selling products</span>
				</div>
				<div class="dpanalytics-top-products" id="dpa-top-products">
					<!-- Populated by JavaScript -->
				</div>
			</div>
		</div>

		<!-- Sidebar Charts Section -->
		<div class="dpanalytics-sidebar-section">
			<!-- Sales by Channel -->
			<div class="dpanalytics-chart-card dpanalytics-chart-card-tall">
				<div class="dpanalytics-chart-header">
					<h3>Sales by Channel</h3>
					<span class="dpanalytics-chart-subtitle">Traffic sources</span>
				</div>
				<div class="dpanalytics-chart-wrapper dpanalytics-chart-donut">
					<canvas id="dpa-channel-chart"></canvas>
				</div>
				<div class="dpanalytics-chart-legend" id="dpa-channel-legend">
					<!-- Populated by JavaScript -->
				</div>
			</div>

			<!-- Sales by Device -->
			<div class="dpanalytics-chart-card">
				<div class="dpanalytics-chart-header">
					<h3>Sales by Device</h3>
					<span class="dpanalytics-chart-subtitle">Device breakdown</span>
				</div>
				<div class="dpanalytics-chart-wrapper dpanalytics-chart-donut">
					<canvas id="dpa-device-chart"></canvas>
				</div>
				<div class="dpanalytics-chart-legend" id="dpa-device-legend">
					<!-- Populated by JavaScript -->
				</div>
			</div>
		</div>
	</div>

	<!-- Countries Section -->
	<div class="dpanalytics-chart-card dpanalytics-full-width">
		<div class="dpanalytics-chart-header">
			<h3>Top Countries</h3>
			<span class="dpanalytics-chart-subtitle">Sales by country</span>
		</div>
		<div class="dpanalytics-countries-list" id="dpa-countries-list">
			<!-- Populated by JavaScript -->
		</div>
	</div>
</div>

<style id="dpanalytics-inline-styles">
	/* Inline styles will be injected by JavaScript for custom variables */
</style>
