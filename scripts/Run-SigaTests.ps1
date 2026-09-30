[CmdletBinding()]
param(
    [ValidateSet("Preinstalacion", "Instalacion")]
    [string]$Modo = "Instalacion",

    [ValidateSet("Actual", "PruebaInstalacion")]
    [string]$Entorno = "Actual"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$raizProyecto = Split-Path -Parent $PSScriptRoot
$artisan = Join-Path $raizProyecto "artisan"
$phpunit = Join-Path $raizProyecto "vendor\phpunit\phpunit\phpunit"
$configuracion = Join-Path $raizProyecto "phpunit.installation.xml"

$entornoMigracion = "migration"
$entornoAnterior = [Environment]::GetEnvironmentVariable(
    "APP_ENV",
    "Process"
)

$codigoSalida = 1
$ubicacionCambiada = $false

try {
    $php = Get-Command php -CommandType Application -ErrorAction Stop

    if (-not (Test-Path -LiteralPath $artisan -PathType Leaf)) {
        throw "No se encontro Artisan en: $artisan"
    }

    if ($Entorno -eq "PruebaInstalacion") {
        foreach ($nombreArchivo in @(
            ".env.installation",
            ".env.installation-migration"
        )) {
            $rutaEntorno = Join-Path $raizProyecto $nombreArchivo

            if (-not (Test-Path -LiteralPath $rutaEntorno -PathType Leaf)) {
                throw "Falta el archivo de entorno: $nombreArchivo"
            }
        }

        $cacheConfiguracion = Join-Path $raizProyecto "bootstrap\cache\config.php"

        if (Test-Path -LiteralPath $cacheConfiguracion) {
            throw "Existe configuracion cacheada. Debemos revisarla antes de seleccionar el entorno de pruebas."
        }

        if ([Environment]::GetEnvironmentVariable("APP_CONFIG_CACHE", "Process")) {
            throw "Existe una ruta personalizada APP_CONFIG_CACHE. Debemos revisarla antes de ejecutar las pruebas."
        }

        $entornoMigracion = "installation-migration"
    }

    Push-Location -LiteralPath $raizProyecto
    $ubicacionCambiada = $true

    Write-Host "SIGA - Bateria de pruebas"
    Write-Host "Modo: $Modo"
    Write-Host "Entorno: $Entorno"
    Write-Host "Etapa: comprobacion del estado de instalacion."

    & $php.Source $artisan siga:check-installation "--env=$entornoMigracion"
    $codigoSalida = $LASTEXITCODE

    if ($codigoSalida -ne 0) {
        Write-Host "El diagnostico requiere atencion."
        Write-Host "Las pruebas de instalacion no se ejecutaron."
    } elseif ($Modo -eq "Instalacion") {
        if (-not (Test-Path -LiteralPath $phpunit -PathType Leaf)) {
            throw "No se encontro PHPUnit en: $phpunit"
        }

        if (-not (Test-Path -LiteralPath $configuracion -PathType Leaf)) {
            throw "Falta la configuracion: $configuracion"
        }

        if ($Entorno -eq "PruebaInstalacion") {
            [Environment]::SetEnvironmentVariable(
                "APP_ENV",
                "installation",
                "Process"
            )
        }

        Write-Host "Etapa: pruebas de instalacion con la conexion de aplicacion."

        & $php.Source $phpunit `
            --configuration $configuracion `
            --testdox `
            --colors=never `
            --do-not-cache-result `
            --fail-on-empty-test-suite

        $codigoSalida = $LASTEXITCODE

        if ($codigoSalida -eq 0) {
            Write-Host "Resultado: pruebas aprobadas."
        } else {
            Write-Host "Resultado: pruebas con incidencias."
        }
    } else {
        Write-Host "Resultado: diagnostico de preinstalacion satisfactorio."
    }

    Write-Host "Codigo de salida: $codigoSalida"
} catch {
    Write-Host ("Error: " + $_.Exception.Message)
    $codigoSalida = 1
} finally {
    [Environment]::SetEnvironmentVariable(
        "APP_ENV",
        $entornoAnterior,
        "Process"
    )

    if ($ubicacionCambiada) {
        Pop-Location
    }
}

exit $codigoSalida