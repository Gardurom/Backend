[CmdletBinding()]
param(
    [ValidateSet("Preinstalacion", "Instalacion", "Funcional", "Completa")]
    [string]$Modo = "Instalacion",

    [ValidateSet("Actual", "PruebaInstalacion")]
    [string]$Entorno = "Actual"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$raizProyecto = Split-Path -Parent $PSScriptRoot
$artisan = Join-Path $raizProyecto "artisan"
$phpunit = Join-Path $raizProyecto "vendor\phpunit\phpunit\phpunit"

$entornoMigracion = "migration"
$entornoAnterior = [Environment]::GetEnvironmentVariable(
    "APP_ENV",
    "Process"
)

$codigoSalida = 1
$ubicacionCambiada = $false

try {
    if (
        $Modo -in @("Funcional", "Completa") -and
        $Entorno -ne "PruebaInstalacion"
    ) {
        throw "Los modos Funcional y Completa requieren -Entorno PruebaInstalacion."
    }

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

    $baterias = @()

    if ($Modo -in @("Instalacion", "Completa")) {
        $baterias += @{
            Nombre = "Instalacion"
            Archivo = "phpunit.installation.xml"
        }
    }

    if ($Modo -in @("Funcional", "Completa")) {
        $baterias += @{
            Nombre = "Funcional"
            Archivo = "phpunit.functional.xml"
        }
    }

    if ($baterias.Count -gt 0) {
        if (-not (Test-Path -LiteralPath $phpunit -PathType Leaf)) {
            throw "No se encontro PHPUnit en: $phpunit"
        }

        foreach ($bateria in $baterias) {
            $rutaConfiguracion = Join-Path $raizProyecto $bateria.Archivo

            if (-not (Test-Path -LiteralPath $rutaConfiguracion -PathType Leaf)) {
                throw "Falta la configuracion: $rutaConfiguracion"
            }
        }
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
        Write-Host "Las baterias seleccionadas no se ejecutaron."
    } elseif ($Modo -eq "Preinstalacion") {
        Write-Host "Resultado: diagnostico de preinstalacion satisfactorio."
    } else {
        if ($Entorno -eq "PruebaInstalacion") {
            [Environment]::SetEnvironmentVariable(
                "APP_ENV",
                "installation",
                "Process"
            )
        }

        foreach ($bateria in $baterias) {
            $rutaConfiguracion = Join-Path $raizProyecto $bateria.Archivo

            Write-Host ("Etapa: bateria " + $bateria.Nombre)

            & $php.Source $phpunit `
                --configuration $rutaConfiguracion `
                --testdox `
                --colors=never `
                --do-not-cache-result `
                --fail-on-empty-test-suite

            $codigoSalida = $LASTEXITCODE

            if ($codigoSalida -ne 0) {
                Write-Host ("La bateria " + $bateria.Nombre + " presento incidencias.")
                break
            }
        }

        if ($codigoSalida -eq 0) {
            Write-Host "Resultado: todas las baterias seleccionadas fueron aprobadas."
        } else {
            Write-Host "Resultado: revision requerida."
        }
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