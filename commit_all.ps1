$status = git status --porcelain
foreach ($line in $status) {
    if ([string]::IsNullOrWhiteSpace($line)) { continue }
    $statusCode = $line.Substring(0, 2)
    $file = $line.Substring(3).Trim(' "')
    
    # Normalize slashes for regex matching
    $normalizedFile = $file -replace '\\', '/'

    if ($normalizedFile -match '^\.env' -or $normalizedFile -match '^\.claude/' -or $normalizedFile -eq 'CLAUDE.md' -or $normalizedFile -match '^docs/') {
        Write-Host "Skipping forbidden file: $file"
        continue
    }

    $basename = Split-Path $file -Leaf
    $dirname = Split-Path $file -Parent

    $action = if ($statusCode -eq '??') { 'add' } else { 'update' }

    $type = "chore"
    $scope = "core"
    $desc = "$action $basename"

    if ($basename -eq ".gitignore") {
        $type = "chore"
        $scope = "git"
        $desc = "$action gitignore config"
    } elseif ($dirname -match "Models") {
        $type = "feat"
        $scope = "model"
        $modelName = $basename -replace '\.php$', ''
        $desc = "$action $modelName"
    } elseif ($dirname -match "migrations") {
        $type = "feat"
        $scope = "migration"
        $migName = $basename -replace '^\d{4}_\d{2}_\d{2}_\d{6}_', '' -replace '\.php$', '' -replace '_', ' '
        $desc = "$action $migName"
    } elseif ($dirname -match "tests") {
        $type = "test"
        $scope = "feature"
        $testName = $basename -replace '\.php$', ''
        $desc = "$action $testName"
    }

    $scope = $scope.ToLower()
    $desc = $desc.ToLower()

    $commitMsg = "$type($scope): $desc"
    if ($commitMsg.Length -gt 72) {
        $commitMsg = $commitMsg.Substring(0, 72)
    }

    Write-Host "Committing $file -> $commitMsg"
    git add $file
    git commit -m $commitMsg
}
