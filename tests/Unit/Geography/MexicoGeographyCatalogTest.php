<?php

namespace Tests\Unit\Geography;

use App\Geography\MexicoGeographyCatalog;
use PHPUnit\Framework\TestCase;

final class MexicoGeographyCatalogTest extends TestCase
{
    public function test_country_contains_official_mexico_data(): void
    {
        self::assertSame(
            [
                'codigo_alpha2' => 'MX',
                'codigo_alpha3' => 'MEX',
                'codigo_num3' => '484',
                'nombre' => 'México',
                'nacionalidad_masculina' => 'Mexicano',
                'nacionalidad_femenina' => 'Mexicana',
                'activo' => true,
            ],
            MexicoGeographyCatalog::country()
        );
    }

    public function test_territories_match_official_keys_and_names(): void
    {
        $expected = [
            '01' => 'Aguascalientes',
            '02' => 'Baja California',
            '03' => 'Baja California Sur',
            '04' => 'Campeche',
            '05' => 'Coahuila de Zaragoza',
            '06' => 'Colima',
            '07' => 'Chiapas',
            '08' => 'Chihuahua',
            '09' => 'Ciudad de México',
            '10' => 'Durango',
            '11' => 'Guanajuato',
            '12' => 'Guerrero',
            '13' => 'Hidalgo',
            '14' => 'Jalisco',
            '15' => 'México',
            '16' => 'Michoacán de Ocampo',
            '17' => 'Morelos',
            '18' => 'Nayarit',
            '19' => 'Nuevo León',
            '20' => 'Oaxaca',
            '21' => 'Puebla',
            '22' => 'Querétaro',
            '23' => 'Quintana Roo',
            '24' => 'San Luis Potosí',
            '25' => 'Sinaloa',
            '26' => 'Sonora',
            '27' => 'Tabasco',
            '28' => 'Tamaulipas',
            '29' => 'Tlaxcala',
            '30' => 'Veracruz de Ignacio de la Llave',
            '31' => 'Yucatán',
            '32' => 'Zacatecas',
        ];

        $territories = MexicoGeographyCatalog::territories();

        self::assertCount(32, $territories);

        self::assertSame(
            $expected,
            array_column(
                $territories,
                'nombre',
                'clave_oficial'
            )
        );
    }

    public function test_territories_have_valid_structure_and_unique_keys(): void
    {
        $territories = MexicoGeographyCatalog::territories();

        $keys = [];

        foreach ($territories as $territory) {
            self::assertSame(
                [
                    'tipo_territorio',
                    'clave_oficial',
                    'nombre',
                    'activo',
                ],
                array_keys($territory)
            );

            self::assertSame(
                'ENTIDAD_FEDERATIVA',
                $territory['tipo_territorio']
            );

            self::assertMatchesRegularExpression(
                '/^[0-9]{2}$/',
                $territory['clave_oficial']
            );

            self::assertNotSame('', $territory['nombre']);

            self::assertTrue($territory['activo']);

            $keys[] = $territory['clave_oficial'];
        }

        self::assertCount(32, array_unique($keys));
    }
}