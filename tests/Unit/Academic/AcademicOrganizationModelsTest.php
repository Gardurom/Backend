<?php

use App\Models\AcademicCohort;
use App\Models\AcademicProgram;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PHPUnit\Framework\TestCase;

final class AcademicOrganizationModelsTest extends TestCase
{
    /**
     * Resolver de conexiones exclusivo para pruebas.
     * No establece conexiones reales ni ejecuta SQL.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $connection = new \Illuminate\Database\PostgresConnection(
            static function () {
                throw new \LogicException(
                    'Las conexiones reales estan prohibidas en esta prueba.'
                );
            },
            'academic_unit_only',
            '',
            [
                'driver' => 'pgsql',
                'name' => 'academic_unit_only',
            ]
        );

        $resolver = new \Illuminate\Database\ConnectionResolver([
            'academic_unit_only' => $connection,
        ]);

        $resolver->setDefaultConnection('academic_unit_only');

        \Illuminate\Database\Eloquent\Model::setConnectionResolver(
            $resolver
        );
    }

    protected function tearDown(): void
    {
        \Illuminate\Database\Eloquent\Model::unsetConnectionResolver();

        parent::tearDown();
    }

    public function test_program_identity(): void
    {
        $model = new AcademicProgram();

        self::assertSame(
            'institutional.academic_programs',
            $model->getTable()
        );

        self::assertSame(
            'id_programa',
            $model->getKeyName()
        );

        self::assertFalse($model->getIncrementing());
        self::assertSame('string', $model->getKeyType());
    }

    public function test_cohort_identity(): void
    {
        $model = new AcademicCohort();

        self::assertSame(
            'institutional.academic_cohorts',
            $model->getTable()
        );

        self::assertSame(
            'id_cohorte',
            $model->getKeyName()
        );

        self::assertFalse($model->getIncrementing());
        self::assertSame('string', $model->getKeyType());
    }

    public function test_program_fillable_fields(): void
    {
        $model = new AcademicProgram();

        self::assertEqualsCanonicalizing(
            ['codigo', 'nombre'],
            $model->getFillable()
        );
    }

    public function test_cohort_fillable_fields(): void
    {
        $model = new AcademicCohort();

        self::assertEqualsCanonicalizing(
            ['id_programa', 'tipo', 'numero', 'anio'],
            $model->getFillable()
        );
    }

    public function test_program_has_many_cohorts(): void
    {
        $relation = (new AcademicProgram())->cohorts();

        self::assertInstanceOf(HasMany::class, $relation);

        self::assertSame(
            'institutional.academic_cohorts',
            $relation->getRelated()->getTable()
        );

        self::assertSame(
            'id_programa',
            $relation->getForeignKeyName()
        );

        self::assertSame(
            'id_programa',
            $relation->getLocalKeyName()
        );
    }

    public function test_cohort_belongs_to_program(): void
    {
        $relation = (new AcademicCohort())->program();

        self::assertInstanceOf(BelongsTo::class, $relation);

        self::assertSame(
            'institutional.academic_programs',
            $relation->getRelated()->getTable()
        );

        self::assertSame(
            'id_programa',
            $relation->getForeignKeyName()
        );

        self::assertSame(
            'id_programa',
            $relation->getOwnerKeyName()
        );
    }
}