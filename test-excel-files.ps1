# PowerShell Script to Test Excel Files
# Tests each Excel file by uploading to the API

$testDataPath = "test-data"
$resultsPath = "test-data/results"
$apiUrl = "http://localhost:8000/api/import/excel"

# Create results directory
if (!(Test-Path $resultsPath)) {
    New-Item -ItemType Directory -Path $resultsPath | Out-Null
}

# Get all Excel files
$excelFiles = Get-ChildItem -Path $testDataPath -Filter "*.xlsx" | Sort-Object Length

Write-Host "=" -NoNewline -ForegroundColor Cyan
Write-Host ("=" * 60) -ForegroundColor Cyan
Write-Host "  Excel File Testing - Automated" -ForegroundColor Yellow
Write-Host "=" -NoNewline -ForegroundColor Cyan
Write-Host ("=" * 60) -ForegroundColor Cyan
Write-Host ""
Write-Host "Found $($excelFiles.Count) Excel files to test" -ForegroundColor Green
Write-Host ""

$results = @()

foreach ($file in $excelFiles) {
    Write-Host "[Testing] $($file.Name)" -ForegroundColor Cyan
    Write-Host "  Size: $([math]::Round($file.Length/1KB, 1)) KB" -ForegroundColor Gray
    
    try {
        # Prepare the file for upload
        $filePath = $file.FullName
        $fileName = $file.Name
        
        # Create multipart form data
        $boundary = [System.Guid]::NewGuid().ToString()
        $fileBytes = [System.IO.File]::ReadAllBytes($filePath)
        
        $bodyLines = @(
            "--$boundary",
            "Content-Disposition: form-data; name=`"file`"; filename=`"$fileName`"",
            "Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "",
            [System.Text.Encoding]::GetEncoding("iso-8859-1").GetString($fileBytes),
            "--$boundary--"
        )
        
        $body = $bodyLines -join "`r`n"
        
        # Upload file
        $startTime = Get-Date
        $response = Invoke-RestMethod -Uri $apiUrl -Method Post `
            -ContentType "multipart/form-data; boundary=$boundary" `
            -Body ([System.Text.Encoding]::GetEncoding("iso-8859-1").GetBytes($body))
        $endTime = Get-Date
        $duration = ($endTime - $startTime).TotalSeconds
        
        # Parse results
        if ($response.success) {
            $imported = $response.data.imported
            $skipped = $response.data.skipped
            
            Write-Host "  [OK] Imported: $imported, Skipped: $skipped" -ForegroundColor Green
            Write-Host "  Time: $([math]::Round($duration, 2))s" -ForegroundColor Gray
            
            $results += [PSCustomObject]@{
                File = $fileName
                Size = "$([math]::Round($file.Length/1KB, 1)) KB"
                Imported = $imported
                Skipped = $skipped
                Duration = "$([math]::Round($duration, 2))s"
                Status = "Success"
                Error = ""
            }
        } else {
            Write-Host "  [FAIL] $($response.message)" -ForegroundColor Red
            
            $results += [PSCustomObject]@{
                File = $fileName
                Size = "$([math]::Round($file.Length/1KB, 1)) KB"
                Imported = 0
                Skipped = 0
                Duration = "$([math]::Round($duration, 2))s"
                Status = "Failed"
                Error = $response.message
            }
        }
        
    } catch {
        Write-Host "  [ERROR] $($_.Exception.Message)" -ForegroundColor Red
        
        $results += [PSCustomObject]@{
            File = $fileName
            Size = "$([math]::Round($file.Length/1KB, 1)) KB"
            Imported = 0
            Skipped = 0
            Duration = "0s"
            Status = "Error"
            Error = $_.Exception.Message
        }
    }
    
    Write-Host ""
    Start-Sleep -Milliseconds 500
}

# Print summary
Write-Host "=" -NoNewline -ForegroundColor Cyan
Write-Host ("=" * 60) -ForegroundColor Cyan
Write-Host "  Summary" -ForegroundColor Yellow
Write-Host "=" -NoNewline -ForegroundColor Cyan
Write-Host ("=" * 60) -ForegroundColor Cyan
Write-Host ""

$results | Format-Table -AutoSize

# Export to CSV
$results | Export-Csv -Path "$resultsPath/upload-results.csv" -NoTypeInformation -Encoding UTF8
Write-Host "Results saved to: $resultsPath/upload-results.csv" -ForegroundColor Green

# Create summary report
$totalImported = ($results | Measure-Object -Property Imported -Sum).Sum
$successCount = ($results | Where-Object { $_.Status -eq "Success" }).Count

$summaryReport = @"
# Excel Upload Test Results

**Date:** $(Get-Date -Format "yyyy-MM-dd HH:mm:ss")
**Files Tested:** $($results.Count)
**Successful:** $successCount
**Total Records Imported:** $totalImported

## Detailed Results

| File | Size | Imported | Skipped | Time | Status |
|------|------|----------|---------|------|--------|
$($results | ForEach-Object { "| $($_.File) | $($_.Size) | $($_.Imported) | $($_.Skipped) | $($_.Duration) | $($_.Status) |" } | Out-String)

## Next Steps

1. Check `decision.html` to verify records
2. Test matching accuracy for suppliers and banks
3. Verify date and number formatting

"@

$summaryReport | Out-File -FilePath "$resultsPath/summary.md" -Encoding UTF8
Write-Host "Summary report: $resultsPath/summary.md" -ForegroundColor Green
