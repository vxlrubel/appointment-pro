@echo off
setlocal EnableExtensions DisableDelayedExpansion
set "PX_PACKAGE_SCRIPT=%~f0"

where node.exe >nul 2>&1
if errorlevel 1 (
    echo ERROR: Node.js is required. Install the Node.js version documented in README.md.
    exit /b 1
)
where npm.cmd >nul 2>&1
if errorlevel 1 (
    echo ERROR: npm.cmd was not found. Install Node.js and npm, then try again.
    exit /b 1
)

pushd "%~dp0src\admin"
if errorlevel 1 (
    echo ERROR: The src\admin directory could not be opened.
    exit /b 1
)
echo Building the admin and public applications...
call npm.cmd run build:all
if errorlevel 1 (
    popd
    echo ERROR: The build failed. No new plugin ZIP was created.
    exit /b 1
)
popd

powershell.exe -NoLogo -NoProfile -NonInteractive -ExecutionPolicy Bypass -Command "$source = [IO.File]::ReadAllText($env:PX_PACKAGE_SCRIPT); $marker = '# POWERSHELL' + '-BEGIN'; & ([scriptblock]::Create($source.Substring($source.IndexOf($marker) + $marker.Length)))"
if errorlevel 1 (
    echo ERROR: Plugin packaging failed.
    exit /b 1
)
exit /b 0

# POWERSHELL-BEGIN
$ErrorActionPreference = 'Stop'
$temporaryZip = $null
$archive = $null
try {
    Add-Type -AssemblyName System.IO.Compression
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $pluginRoot = [IO.Path]::GetDirectoryName([IO.Path]::GetFullPath($env:PX_PACKAGE_SCRIPT))
    $slug = 'px-appointment-pro'
    $entryFile = Join-Path $pluginRoot 'appointment-pro.php'
    $header = [IO.File]::ReadAllText($entryFile)
    $versionMatch = [regex]::Match($header, '(?m)^\s*\*\s*Version:\s*([0-9A-Za-z.+_-]+)\s*$')
    if (-not $versionMatch.Success) { throw 'The plugin header does not contain a valid Version value.' }

    # This plugin currently has no third-party production PHP packages and
    # registers its own PSR-4 fallback autoloader. Never ship development stubs.
    $composerPath = Join-Path $pluginRoot 'composer.json'
    if (Test-Path -LiteralPath $composerPath) {
        $composerConfig = Get-Content -LiteralPath $composerPath -Raw | ConvertFrom-Json
        $runtimePackages = @($composerConfig.require.PSObject.Properties | Where-Object {
            $_.Name -ne 'php' -and $_.Name -notlike 'ext-*' -and $_.Name -notlike 'lib-*'
        })
        if ($runtimePackages.Count -gt 0) {
            throw 'Production Composer dependencies were added. Update this packager to include their production vendor files before releasing.'
        }
    }

    $files = [Collections.Generic.List[string]]::new()
    foreach ($name in @('appointment-pro.php', 'uninstall.php', 'README.md', 'FEATURE-PARITY.md', 'LICENSE', 'LICENSE.txt', 'COPYING', 'readme.txt')) {
        $path = Join-Path $pluginRoot $name
        if (Test-Path -LiteralPath $path -PathType Leaf) { $files.Add($path) }
    }
    foreach ($directory in @('inc', 'lib', 'lang', 'languages')) {
        $path = Join-Path $pluginRoot $directory
        if (-not (Test-Path -LiteralPath $path -PathType Container)) { continue }
        $extensions = switch ($directory) {
            'inc' { @('.php') }
            'lib' { @('.js', '.css', '.json', '.woff', '.woff2', '.ttf', '.otf', '.eot', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp', '.avif', '.ico', '.wasm') }
            default { @('.mo', '.json', '.po') }
        }
        foreach ($file in Get-ChildItem -LiteralPath $path -Recurse -File) {
            if ($extensions -contains $file.Extension.ToLowerInvariant()) { $files.Add($file.FullName) }
        }
    }
    $required = @(
        'appointment-pro.php', 'uninstall.php', 'inc/AppointmentPro.php',
        'inc/Installer.php', 'inc/PublicSite/Frontend.php', 'inc/Rest/Rest.php',
        'lib/admin/main.js', 'lib/admin/main.css',
        'lib/admin/chunk-DatabaseView.js', 'lib/admin/chunk-DocumentView.js',
        'lib/public/public.js', 'lib/public/public.css', 'lib/editor/booking-block.js'
    )
    $relativeFiles = @($files | ForEach-Object { $_.Substring($pluginRoot.Length + 1).Replace('\', '/') })
    foreach ($name in $required) {
        if ($relativeFiles -notcontains $name) { throw "Required runtime file is missing: $name" }
    }

    $outputDirectory = Join-Path $pluginRoot 'dist'
    [IO.Directory]::CreateDirectory($outputDirectory) | Out-Null
    $outputZip = Join-Path $outputDirectory ($slug + '-' + $versionMatch.Groups[1].Value + '.zip')
    $temporaryZip = Join-Path $outputDirectory ('.' + $slug + '-' + [guid]::NewGuid().ToString('N') + '.tmp')
    $archive = [IO.Compression.ZipFile]::Open($temporaryZip, [IO.Compression.ZipArchiveMode]::Create)
    foreach ($path in ($files | Sort-Object -Unique)) {
        $relative = $path.Substring($pluginRoot.Length + 1).Replace('\', '/')
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, $path, ($slug + '/' + $relative), [IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
    $archive.Dispose()
    $archive = $null

    # Inspect the finished archive before replacing the previous release.
    $archive = [IO.Compression.ZipFile]::OpenRead($temporaryZip)
    $entries = @($archive.Entries | ForEach-Object { $_.FullName })
    foreach ($name in $required) {
        if ($entries -notcontains ($slug + '/' + $name)) { throw "Archive validation failed: $name" }
    }
    if (@($entries | Where-Object { $_ -notlike ($slug + '/*') -or $_ -match '\\|(^|/)\.\./|\.map$|/(src|node_modules|vendor|tests|tools|praxleo-booking|\.git|\.vscode|dist)/' }).Count -gt 0) {
        throw 'The archive contains an unexpected path or development file.'
    }
    $archive.Dispose()
    $archive = $null
    Move-Item -LiteralPath $temporaryZip -Destination $outputZip -Force
    $temporaryZip = $null
    Write-Host ''
    Write-Host ('Created: ' + $outputZip)
    Write-Host ('Packaged ' + $entries.Count + ' files under ' + $slug + '/.')
    Write-Host 'Upload this ZIP through WordPress > Plugins > Add New > Upload Plugin.'
    exit 0
} catch {
    Write-Host ('ERROR: ' + $_.Exception.Message) -ForegroundColor Red
    exit 1
} finally {
    if ($null -ne $archive) { $archive.Dispose() }
    if ($temporaryZip -and (Test-Path -LiteralPath $temporaryZip -PathType Leaf)) {
        Remove-Item -LiteralPath $temporaryZip -Force
    }
}
