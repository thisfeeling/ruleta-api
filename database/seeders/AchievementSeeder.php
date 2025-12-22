<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AchievementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $achievements = [
            // Millionaire Achievements
            [
                'key' => 'millionaire_perfect',
                'name_es' => '¡Cerebro Total!',
                'name_en' => 'Total Brain!',
                'description_es' => 'Responde todas las preguntas correctamente',
                'description_en' => 'Answer all questions correctly',
                'rarity' => 'legendary',
                'points' => 500,
                'is_secret' => false,
            ],
            [
                'key' => 'millionaire_speed_demon',
                'name_es' => 'Relámpago Mental',
                'name_en' => 'Speed Demon',
                'description_es' => 'Responde 5 preguntas en menos de 3 segundos cada una',
                'description_en' => 'Answer 5 questions in less than 3 seconds each',
                'rarity' => 'epic',
                'points' => 300,
                'is_secret' => false,
            ],

            // Rope Achievements
            [
                'key' => 'rope_click_master',
                'name_es' => 'Dedo Rápido',
                'name_en' => 'Click Master',
                'description_es' => 'Haz más de 200 clicks en la batalla',
                'description_en' => 'Make more than 200 clicks in battle',
                'rarity' => 'rare',
                'points' => 200,
                'is_secret' => false,
            ],

            // Spell Achievements
            [
                'key' => 'spell_perfect',
                'name_es' => 'Ortografía Perfecta',
                'name_en' => 'Perfect Spelling',
                'description_es' => 'Deletrea tu palabra correctamente al primer intento',
                'description_en' => 'Spell your word correctly on first try',
                'rarity' => 'common',
                'points' => 100,
                'is_secret' => false,
            ],

            // Roulette Achievements
            [
                'key' => 'roulette_lucky',
                'name_es' => 'Súper Suertudo',
                'name_en' => 'Super Lucky',
                'description_es' => 'Gana más de 800 puntos en la ruleta',
                'description_en' => 'Win more than 800 points in roulette',
                'rarity' => 'epic',
                'points' => 400,
                'is_secret' => false,
            ],

            // Bonus Games
            [
                'key' => 'word_search_speed',
                'name_es' => 'Vista de Águila',
                'name_en' => 'Eagle Eye',
                'description_es' => 'Encuentra 5 palabras en menos de 30 segundos',
                'description_en' => 'Find 5 words in less than 30 seconds',
                'rarity' => 'rare',
                'points' => 250,
                'is_secret' => false,
            ],
            [
                'key' => 'flappy_survivor',
                'name_es' => 'Pájaro Invencible',
                'name_en' => 'Invincible Bird',
                'description_es' => 'Sobrevive más de 60 segundos',
                'description_en' => 'Survive more than 60 seconds',
                'rarity' => 'epic',
                'points' => 350,
                'is_secret' => false,
            ],

            // General Achievements
            [
                'key' => 'first_blood',
                'name_es' => 'Primera Sangre',
                'name_en' => 'First Blood',
                'description_es' => 'Sé el primer jugador eliminado',
                'description_en' => 'Be the first player eliminated',
                'rarity' => 'common',
                'points' => 50,
                'is_secret' => true,
            ],
            [
                'key' => 'show_winner',
                'name_es' => '¡Campeón!',
                'name_en' => 'Champion!',
                'description_es' => 'Gana el show completo',
                'description_en' => 'Win the entire show',
                'rarity' => 'legendary',
                'points' => 1000,
                'is_secret' => false,
            ],
        ];

        foreach ($achievements as $achievement) {
            DB::table('achievements')->insert(array_merge($achievement, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
