$ErrorActionPreference = 'Stop'
$pp = New-Object -ComObject PowerPoint.Application
$pp.Visible = [Microsoft.Office.Core.MsoTriState]::msoTrue
$src = 'D:\www\klinikdent\presentasi\Presentasi_Kemitraan_KlinikDent.pptx'
$out = 'D:\www\klinikdent\presentasi\_preview'
New-Item -ItemType Directory -Force -Path $out | Out-Null
$pres = $pp.Presentations.Open($src, $true, $false, $false)
$pres.SaveAs($out, 18)
$pres.Close()
$pp.Quit()
Write-Output 'exported'
Get-ChildItem $out | Select-Object -ExpandProperty Name
