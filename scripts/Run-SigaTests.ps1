[CmdletBinding()]
param(
    [ValidateSet("Preinstalacion", "Instalacion")]
    [string]$Modo = "Instalacion"
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

$raizProyecto = Split-Path -Parent $PSScriptRoot
$artisan = Join-Path $raizProyecto "artisan"
$phpunit = Join-Path $raizProyecto "vendor\phpunit\phpunit\phpunit"
$configuracion = Join-Path $raizProyecto "phpunit.installation.xml"

$codigoSalida = 1
$ubicacionCambiada = $false

try {
    $php = Get-Command php -CommandType Application -ErrorAction Stop

    if (-not (Test-Path -LiteralPath $artisan -PathType Leaf)) {
        throw "No se encontro Artisan en: $artisan"
    }

    Push-Location -LiteralPath $raizProyecto
    $ubicacionCambiada = $true

    Write-Host "SIGA - Bateria de pruebas"
    Write-Host "Modo: $Modo"
    Write-Host "Etapa: comprobacion del estado de instalacion."

    & $php.Source $artisan siga:check-installation --env=migration
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
    if ($ubicacionCambiada) {
        Pop-Location
    }
}

exit $codigoSalida