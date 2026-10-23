<?php

namespace App\Geography;

final class MexicoGeographyCatalog
{
    public static function country(): array
    {
        return [
            'codigo_alpha2' => 'MX',
            'codigo_alpha3' => 'MEX',
            'codigo_num3' => '484',
            'nombre' => 'México',
            'nacionalidad_masculina' => 'Mexicano',
            'nacionalidad_femenina' => 'Mexicana',
            'activo' => true,
        ];
    }

    public static function territories(): array
    {
        $names = [
            'Aguascalientes',
            'Baja California',
            'Baja California Sur',
            'Campeche',
            'Coahuila de Zaragoza',
            'Colima',
            'Chiapas',
            'Chihuahua',
            'Ciudad de México',
            'Durango',
            'Guanajuato',
            'Guerrero',
            'Hidalgo',
            'Jalisco',
            'México',
            'Michoacán de Ocampo',
            'Morelos',
            'Nayarit',
            'Nuevo León',
            'Oaxaca',
            'Puebla',
            'Querétaro',
            'Quintana Roo',
            'San Luis Potosí',
            'Sinaloa',
            'Sonora',
            'Tabasco',
            'Tamaulipas',
            'Tlaxcala',
            'Veracruz de Ignacio de la Llave',
            'Yucatán',
            'Zacatecas',
        ];

        $territories = [];

        foreach ($names as $index => $name) {
            $territories[] = [
                'tipo_territorio' => 'ENTIDAD_FEDERATIVA',
                'clave_oficial' => str_pad(
                    (string) ($index + 1),
                    2,
                    '0',
                    STR_PAD_LEFT
                ),
                'nombre' => $name,
                'activo' => true,
            ];
        }

        return $territories;
    }
}