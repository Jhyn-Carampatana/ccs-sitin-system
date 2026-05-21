</div>
<script>
function exportPDF() { 
  html2pdf().from(document.querySelector('.main-content')).set({ margin: 0.5, filename: 'CCS_Report.pdf' }).save(); 
}
function exportExcel() {
  let wsData = [['Report', 'Value']];
  wsData.push(['Generated', new Date().toLocaleString()]);
  const ws = XLSX.utils.aoa_to_sheet(wsData);
  const wb = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(wb, ws, 'Report');
  XLSX.writeFile(wb, `CCS_Report_<?php echo date('Y-m-d'); ?>.xlsx`);
}
</script>
</body>
</html>