<?php

namespace App\Support;

use App\Enums\StandardCoursePracticalKind;
use App\Models\StandardCoursePlan;
use Illuminate\Support\Str;

class Class11CbsePhysicsStarter
{
    public const REFERENCE_BOOK = 'NCERT Class 11 Physics';

    /**
     * @return list<array{name: string, estimated_marks: int, topics: list<array{name: string, planned_minutes: int, reference_book: string, dpp_count: int, quiz_count: int, test_count: int}>}>
     */
    public static function chapters(): array
    {
        return [
            self::chapter('Units and Measurements', 5, [
                self::topic('Need for measurement, units, SI units, significant figures, uncertainty', 160),
                self::topic('Dimensions and dimensional analysis', 160, true),
            ]),
            self::chapter('Motion in a Straight Line', 6, [
                self::topic('Frame of reference, straight-line motion, differentiation and integration', 192),
                self::topic('Uniform and non-uniform motion, average speed and velocity, instantaneous velocity', 192),
                self::topic('Uniformly accelerated motion, graphs, and the equations of motion', 192, true),
            ]),
            self::chapter('Motion in a Plane', 6, [
                self::topic('Scalars and vectors, addition, resolution, scalar and vector products', 192),
                self::topic('Projectile motion and uniform circular motion', 192, true),
            ]),
            self::chapter('Laws of Motion', 6, [
                self::topic('Force, inertia, Newton\'s three laws, impulse', 140),
                self::topic('Conservation of linear momentum', 140),
                self::topic('Equilibrium and friction', 140),
                self::topic('Centripetal force, level road, banked road', 140, true),
            ]),
            self::chapter('Work, Energy and Power', 5, [
                self::topic('Work, kinetic energy, work-energy theorem, power', 280),
                self::topic('Potential energy, spring, vertical circle, collisions in one and two dimensions', 280, true),
            ]),
            self::chapter('System of Particles and Rotational Motion', 6, [
                self::topic('Centre of mass', 180),
                self::topic('Torque, angular momentum, conservation of angular momentum', 180),
                self::topic('Equilibrium and equations of rotational motion', 180),
                self::topic('Moment of inertia and radius of gyration', 180, true),
            ]),
            self::chapter('Gravitation', 6, [
                self::topic('Kepler\'s laws, universal law, variation of g', 240),
                self::topic('Gravitational potential, escape speed, satellite speed and energy', 240, true),
            ]),
            self::chapter('Mechanical Properties of Solids', 4, [
                self::topic('Stress, strain, Hooke\'s law, moduli, Poisson\'s ratio, elastic energy', 137),
                self::topic('Applications of elastic behaviour', 137, true),
            ]),
            self::chapter('Mechanical Properties of Fluids', 4, [
                self::topic('Pressure, Pascal\'s law, hydraulic lift and brakes', 137),
                self::topic('Viscosity, Stokes\' law, streamline and turbulent flow, Bernoulli\'s theorem', 137),
                self::topic('Surface tension, drops, bubbles, capillary rise', 137, true),
            ]),
            self::chapter('Thermal Properties of Matter', 4, [
                self::topic('Heat, temperature, expansion, specific heat, calorimetry, latent heat', 137),
                self::topic('Conduction, convection, radiation, Wien\'s law, Stefan\'s law', 138, true),
            ]),
            self::chapter('Thermodynamics', 4, [
                self::topic('Thermal equilibrium, zeroth law, heat, work, internal energy, first and second laws', 240),
                self::topic('Isothermal, adiabatic, reversible, irreversible, and cyclic processes', 240, true),
            ]),
            self::chapter('Kinetic Theory', 4, [
                self::topic('Equation of state, work done in compressing a gas', 160),
                self::topic('Pressure, rms speed, degrees of freedom, equipartition, mean free path', 160, true),
            ]),
            self::chapter('Oscillations', 5, [
                self::topic('Periodic motion, time period, frequency', 173),
                self::topic('Simple harmonic motion, spring, energy in SHM', 173),
                self::topic('Simple pendulum', 174, true),
            ]),
            self::chapter('Waves', 5, [
                self::topic('Transverse and longitudinal waves, speed of a wave', 173),
                self::topic('Superposition, reflection, standing waves in strings and organ pipes', 173),
                self::topic('Beats', 174, true),
            ]),
        ];
    }

    /**
     * @return list<array{name: string, kind: string, planned_minutes: null, estimated_marks: null}>
     */
    public static function practicals(): array
    {
        $rows = [];

        foreach (self::experimentNames() as $name) {
            $rows[] = self::practical($name, StandardCoursePracticalKind::Experiment);
        }

        foreach (self::activityNames() as $name) {
            $rows[] = self::practical($name, StandardCoursePracticalKind::Activity);
        }

        return $rows;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function formChapters(): array
    {
        $chapters = [];

        foreach (self::chapters() as $chapter) {
            $topics = [];

            foreach ($chapter['topics'] as $topic) {
                $topics[(string) Str::uuid()] = $topic;
            }

            $chapters[(string) Str::uuid()] = [
                'name' => $chapter['name'],
                'estimated_marks' => $chapter['estimated_marks'],
                'topics' => $topics,
            ];
        }

        return $chapters;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function formPracticals(): array
    {
        $practicals = [];

        foreach (self::practicals() as $practical) {
            $practicals[(string) Str::uuid()] = $practical;
        }

        return $practicals;
    }

    public static function apply(StandardCoursePlan $plan): void
    {
        $plan->chapters()->delete();
        $plan->practicals()->delete();

        foreach (self::chapters() as $index => $chapter) {
            $saved = $plan->chapters()->create([
                'name' => $chapter['name'],
                'estimated_marks' => $chapter['estimated_marks'],
                'sort_order' => $index + 1,
            ]);

            foreach ($chapter['topics'] as $topicIndex => $topic) {
                $saved->topics()->create([
                    ...$topic,
                    'sort_order' => $topicIndex + 1,
                ]);
            }
        }

        foreach (self::practicals() as $index => $practical) {
            $plan->practicals()->create([
                ...$practical,
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * @param  list<array{name: string, planned_minutes: int, reference_book: string, dpp_count: int, quiz_count: int, test_count: int}>  $topics
     * @return array{name: string, estimated_marks: int, topics: list<array{name: string, planned_minutes: int, reference_book: string, dpp_count: int, quiz_count: int, test_count: int}>}
     */
    private static function chapter(string $name, int $marks, array $topics): array
    {
        return [
            'name' => $name,
            'estimated_marks' => $marks,
            'topics' => $topics,
        ];
    }

    /**
     * @return array{name: string, planned_minutes: int, reference_book: string, dpp_count: int, quiz_count: int, test_count: int}
     */
    private static function topic(string $name, int $minutes, bool $chapterEnd = false): array
    {
        return [
            'name' => $name,
            'planned_minutes' => $minutes,
            'reference_book' => self::REFERENCE_BOOK,
            'dpp_count' => 1,
            'quiz_count' => $chapterEnd ? 1 : 0,
            'test_count' => $chapterEnd ? 1 : 0,
        ];
    }

    /**
     * @return array{name: string, kind: string, planned_minutes: null, estimated_marks: null}
     */
    private static function practical(string $name, StandardCoursePracticalKind $kind): array
    {
        return [
            'name' => $name,
            'kind' => $kind->value,
            'planned_minutes' => null,
            'estimated_marks' => null,
        ];
    }

    /**
     * @return list<string>
     */
    private static function experimentNames(): array
    {
        return [
            'Measure diameter and volume with Vernier callipers',
            'Measure diameter of a wire and thickness of a sheet with a screw gauge',
            'Volume of an irregular lamina using a screw gauge',
            'Radius of curvature of a spherical surface with a spherometer',
            'Mass of two objects using a beam balance',
            'Weight of a body using the parallelogram law of vectors',
            'Simple pendulum graph and the effective length of a second\'s pendulum',
            'Time period of a simple pendulum with bobs of different masses',
            'Limiting friction and the coefficient of friction',
            'Downward force on an inclined plane against sin of the angle',
            'Young\'s modulus of the material of a given wire',
            'Force constant of a helical spring',
            'Volume and pressure of air at constant temperature',
            'Surface tension of water by capillary rise',
            'Coefficient of viscosity by terminal velocity',
            'Cooling curve of a hot body',
            'Specific heat capacity of a solid by the method of mixtures',
            'Frequency and length of a wire under constant tension using a sonometer',
            'Length of a wire and tension for constant frequency using a sonometer',
            'Speed of sound in air using a resonance tube',
        ];
    }

    /**
     * @return list<string>
     */
    private static function activityNames(): array
    {
        return [
            'Paper scale of a given least count',
            'Mass of a body using a metre scale by the principle of moments',
            'Plot a graph with scales and error bars',
            'Limiting friction for rolling on a horizontal plane',
            'Range of a projectile with the angle of projection',
            'Conservation of energy of a ball on an inclined plane',
            'Dissipation of energy of a simple pendulum',
            'Change of state and cooling curve for molten wax',
            'Effect of heating on a bi-metallic strip',
            'Change in liquid level in a container on heating',
            'Effect of detergent on surface tension by capillary rise',
            'Factors affecting the rate of loss of heat of a liquid',
            'Effect of load on depression of a clamped metre scale',
            'Decrease in pressure when the speed of a fluid increases',
        ];
    }
}
