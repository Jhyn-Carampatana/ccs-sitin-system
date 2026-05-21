<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?php echo $page_title ?? 'CCS Admin'; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { background: #F5F7FB; font-family: 'Inter', sans-serif; color: #1E293B; display: flex; min-height: 100vh; }
  .main-content { margin-left: 260px; flex: 1; padding: 28px 36px; }
  .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 32px; flex-wrap: wrap; gap: 16px; }
  .page-breadcrumb h1 { font-size: 26px; font-weight: 700; }
  .btn-group { display: flex; gap: 12px; flex-wrap: wrap; }
  .btn { padding: 10px 20px; border-radius: 40px; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; font-size: 14px; transition: all 0.2s; }
  .btn-pdf { background: #DC2626; color: white; }
  .btn-pdf:hover { background: #B91C1C; }
  .btn-excel { background: #16A34A; color: white; }
  .btn-excel:hover { background: #15803D; }
  .btn-print { background: #2563EB; color: white; }
  .btn-print:hover { background: #1D4ED8; }
  .stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 16px; margin-bottom: 32px; }
  .stat-card { background: white; border-radius: 24px; padding: 20px 24px; border: 1px solid #EFF3F8; transition: all 0.2s; }
  .stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); transform: translateY(-2px); }
  .stat-card h4 { font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6C7A91; margin-bottom: 8px; letter-spacing: 0.5px; }
  .stat-card .number { font-size: 32px; font-weight: 800; color: #0F172A; }
  .stat-card .trend { font-size: 12px; margin-top: 8px; color: #10B981; }
  .card { background: white; border-radius: 24px; border: 1px solid #EFF3F8; overflow: hidden; margin-bottom: 32px; }
  .card-header { padding: 20px 24px; border-bottom: 1px solid #F0F2F5; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
  .card-header h3 { font-size: 16px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
  .table-wrapper { overflow-x: auto; border-radius: 16px; }
  table { width: 100%; border-collapse: collapse; }
  th { text-align: left; padding: 14px 16px; font-size: 12px; font-weight: 600; text-transform: uppercase; color: #6C7A91; background: #F8FAFE; border-bottom: 1px solid #EDF2F7; }
  td { padding: 14px 16px; font-size: 14px; color: #1E293B; border-bottom: 1px solid #F1F5F9; }
  tr:hover td { background: #F8FAFE; }
  .status-badge { display: inline-block; padding: 4px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; }
  .status-approved { background: #DCFCE7; color: #15803D; }
  .status-pending { background: #FEF3C7; color: #D97706; }
  .status-rejected { background: #FEE2E2; color: #DC2626; }
  .status-expired { background: #E2E8F0; color: #475569; }
  .btn-approve { background: #10B981; color: white; border: none; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; cursor: pointer; }
  .btn-reject { background: #EF4444; color: white; border: none; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; cursor: pointer; }
  .btn-danger { background: #EF4444; color: white; border: none; padding: 6px 12px; border-radius: 8px; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
  .btn-primary { background: #3B82F6; color: white; border: none; padding: 10px 20px; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
  .btn-primary:hover { background: #2563EB; }
  .charts-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px; margin-bottom: 32px; }
  .chart-card { background: white; border-radius: 24px; padding: 20px; border: 1px solid #EFF3F8; }
  .chart-card h3 { font-size: 14px; font-weight: 600; margin-bottom: 16px; color: #1E293B; }
  .form-group { margin-bottom: 16px; }
  .form-group label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 6px; color: #6C7A91; text-transform: uppercase; letter-spacing: 0.3px; }
  .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px 14px; border: 1px solid #E2E8F0; border-radius: 12px; font-size: 14px; font-family: inherit; }
  .date-badge { background: #F1F5F9; padding: 6px 12px; border-radius: 20px; font-size: 12px; display: flex; align-items: center; gap: 6px; }
  .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
  @media (max-width: 1000px) { .main-content { margin-left: 0; padding: 20px; } .stats-grid { grid-template-columns: repeat(2,1fr); } .charts-row { grid-template-columns: 1fr; } }
  @media print { .sidebar, .btn-group { display: none; } .main-content { margin-left: 0; padding: 0; } }
</style>
</head>
<body>
<?php include 'includes/sidebar.php'; ?>
<div class="main-content">
  <div class="top-header">
    <div class="page-breadcrumb"><h1><?php echo $page_title; ?></h1></div>
    <div class="btn-group">
      <button class="btn btn-pdf" onclick="exportPDF()"><i class="fas fa-file-pdf"></i> Export PDF</button>
      <button class="btn btn-excel" onclick="exportExcel()"><i class="fas fa-file-excel"></i> Export Excel</button>
      <button class="btn btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
    </div>
  </div>