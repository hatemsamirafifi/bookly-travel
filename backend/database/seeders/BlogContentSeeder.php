<?php

namespace Database\Seeders;

use App\Domains\Blog\Models\AuthorProfile;
use App\Domains\Blog\Models\BlogCategory;
use App\Domains\Blog\Models\BlogPost;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class BlogContentSeeder extends Seeder
{
    public function run(): void
    {
        // Create blog categories
        $travelTips = BlogCategory::firstOrCreate(
            ['slug' => 'travel-tips'],
            [
                'name' => 'Travel Tips',
                'description' => 'Expert advice for smarter travel',
                'is_active' => true,
                'display_order' => 3,
            ]
        );

        $adventure = BlogCategory::firstOrCreate(
            ['slug' => 'adventure'],
            [
                'name' => 'Adventure',
                'description' => 'Thrilling experiences and outdoor activities',
                'is_active' => true,
                'display_order' => 4,
            ]
        );

        $culture = BlogCategory::firstOrCreate(
            ['slug' => 'culture-history'],
            [
                'name' => 'Culture & History',
                'description' => 'Deep dives into local heritage',
                'is_active' => true,
                'display_order' => 5,
            ]
        );

        // Create blog authors
        $author1 = $this->createAuthor(
            'marco@bookly.test',
            'Marco Bianchi',
            [
                'en' => 'Food & Culture Writer based in Italy',
                'es' => 'Escritor de Comida y Cultura en Italia',
                'it' => 'Scrittore di Cibo e Cultura in Italia',
            ]
        );

        $author2 = $this->createAuthor(
            'sophie@bookly.test',
            'Sophie Laurent',
            [
                'en' => 'Adventure Travel Specialist',
                'es' => 'Especialista en Viajes de Aventura',
                'it' => 'Specialista Viaggi Avventura',
            ]
        );

        // Get existing category for some posts
        $cityGuides = BlogCategory::where('slug', 'city-guides')->first();
        $foodAndWine = BlogCategory::where('slug', 'food-and-wine')->first();

        // Create blog posts
        $this->createBlogPost(
            'venice-travel-guide-2026',
            $author1,
            $cityGuides,
            [
                'title' => [
                    'en' => 'Complete Venice Travel Guide 2026',
                    'es' => 'Guía Completa de Viajes a Venecia 2026',
                    'it' => 'Guida Completa di Viaggio a Venezia 2026',
                ],
                'excerpt' => [
                    'en' => 'Everything you need to know before visiting the floating city',
                    'es' => 'Todo lo que necesitas saber antes de visitar la ciudad flotante',
                    'it' => 'Tutto quello che devi sapere prima di visitare la città galleggiante',
                ],
                'body' => [
                    'en' => '<p>Venice, the city of canals, continues to enchant visitors from around the world.</p><h2>Best Time to Visit</h2><p>Spring (April-May) and Fall (September-October) offer the best weather and fewer crowds.</p><h2>Must-See Attractions</h2><p>St. Mark\'s Basilica, Doge\'s Palace, and the Grand Canal are essential stops.</p><h2>Getting Around</h2><p>Vaporetto water buses are your best friend for navigating the canals efficiently.</p>',
                    'es' => '<p>Venecia, la ciudad de los canales, sigue encantando a visitantes de todo el mundo.</p><h2>Mejor Época para Visitar</h2><p>Primavera (abril-mayo) y otoño (septiembre-octubre) ofrecen el mejor clima.</p>',
                    'it' => '<p>Venezia, la città dei canali, continua ad incantare visitatori da tutto il mondo.</p><h2>Periodo Migliore</h2><p>Primavera (aprile-maggio) e autunno (settembre-ottobre) offrono il clima migliore.</p>',
                ],
                'is_featured' => true,
                'published_at' => now()->subDays(5),
            ]
        );

        $this->createBlogPost(
            'barcelona-tapas-wine-guide',
            $author1,
            $foodAndWine,
            [
                'title' => [
                    'en' => 'Barcelona Tapas & Wine: A Local\'s Guide',
                    'es' => 'Tapas y Vino en Barcelona: Guía Local',
                    'it' => 'Tapas e Vino a Barcellona: Guida Locale',
                ],
                'excerpt' => [
                    'en' => 'Discover authentic tapas bars and wine cellars beyond the tourist traps',
                    'es' => 'Descubre auténticos bares de tapas más allá de las trampas turísticas',
                    'it' => 'Scopri autentici bar di tapas oltre le trappole turistiche',
                ],
                'body' => [
                    'en' => '<p>Barcelona\'s culinary scene goes far beyond paella and sangria.</p><h2>Best Tapas Neighborhoods</h2><p>El Born and Gràcia offer authentic experiences away from La Rambla crowds.</p><h2>Must-Try Dishes</h2><p>Patatas bravas, pan con tomate, and jamón ibérico are essential.</p><h2>Wine Pairing</h2><p>Try local Catalan wines like Priorat and Penedès with your tapas.</p>',
                    'es' => '<p>La escena culinaria de Barcelona va mucho más allá de la paella.</p><h2>Mejores Barrios</h2><p>El Born y Gràcia ofrecen experiencias auténticas.</p>',
                    'it' => '<p>La scena culinaria di Barcellona va oltre la paella.</p><h2>Quartieri Migliori</h2><p>El Born e Gràcia offrono esperienze autentiche.</p>',
                ],
                'is_featured' => false,
                'published_at' => now()->subDays(8),
            ]
        );

        $this->createBlogPost(
            'paris-hidden-museums',
            $author2,
            $culture,
            [
                'title' => [
                    'en' => '10 Hidden Museums in Paris You\'ve Never Heard Of',
                    'es' => '10 Museos Ocultos en París que Nunca has Oído',
                    'it' => '10 Musei Nascosti a Parigi che Non Hai Mai Sentito',
                ],
                'excerpt' => [
                    'en' => 'Skip the Louvre crowds and explore these hidden cultural gems',
                    'es' => 'Evita las multitudes del Louvre y explora estas joyas culturales',
                    'it' => 'Salta le folle del Louvre ed esplora questi gioielli culturali',
                ],
                'body' => [
                    'en' => '<p>Paris has thousands of museums beyond the famous trio.</p><h2>Musée Nissim de Camondo</h2><p>An 18th-century mansion frozen in time with exquisite decorative arts.</p><h2>Musée de la Chasse et de la Nature</h2><p>A quirky museum blending hunting history with contemporary art.</p><h2>Musée de Montmartre</h2><p>Discover Montmartre\'s artistic history in the very studios artists worked.</p>',
                    'es' => '<p>París tiene miles de museos más allá del famoso trío.</p><h2>Museos Escondidos</h2><p>Descubre arte en mansiones del siglo XVIII.</p>',
                    'it' => '<p>Parigi ha migliaia di musei oltre il famoso trio.</p><h2>Musei Nascosti</h2><p>Scopri arte in palazzi del XVIII secolo.</p>',
                ],
                'is_featured' => true,
                'published_at' => now()->subDays(12),
            ]
        );

        $this->createBlogPost(
            'budget-travel-europe-2026',
            $author2,
            $travelTips,
            [
                'title' => [
                    'en' => 'Budget Travel in Europe 2026: Save Money Without Sacrificing Fun',
                    'es' => 'Viajar con Presupuesto en Europa 2026: Ahorra sin Sacrificar',
                    'it' => 'Viaggiare in Europa con Budget 2026: Risparmia Senza Sacrificare',
                ],
                'excerpt' => [
                    'en' => 'Practical tips to explore Europe on a shoestring budget',
                    'es' => 'Consejos prácticos para explorar Europa con presupuesto ajustado',
                    'it' => 'Consigli pratici per esplorare l\'Europa con budget limitato',
                ],
                'body' => [
                    'en' => '<p>Traveling Europe doesn\'t have to break the bank.</p><h2>Transportation Hacks</h2><p>Book trains 3 months in advance for 50% off. Use Flixbus for intercity travel.</p><h2>Accommodation</h2><p>Hostels aren\'t just for backpackers anymore. Many offer private rooms.</p><h2>Free Activities</h2><p>Most European cities have free walking tours and museum days.</p><h2>Food Budget</h2><p>Lunch menus (menu del día) offer restaurant meals at half the dinner price.</p>',
                    'es' => '<p>Viajar por Europa no tiene que ser caro.</p><h2>Trucos de Transporte</h2><p>Reserva trenes con 3 meses de antelación.</p>',
                    'it' => '<p>Viaggiare in Europa non deve costare una fortuna.</p><h2>Trucchi Trasporto</h2><p>Prenota treni 3 mesi prima.</p>',
                ],
                'is_featured' => false,
                'published_at' => now()->subDays(15),
            ]
        );

        $this->createBlogPost(
            'rome-ancient-history-walking-tour',
            $author1,
            $culture,
            [
                'title' => [
                    'en' => 'Walking Through 2,000 Years of Roman History',
                    'es' => 'Caminando por 2,000 Años de Historia Romana',
                    'it' => 'Camminando Attraverso 2.000 Anni di Storia Romana',
                ],
                'excerpt' => [
                    'en' => 'A chronological walking route through Ancient Rome',
                    'es' => 'Una ruta cronológica a pie por la Roma Antigua',
                    'it' => 'Un percorso cronologico a piedi attraverso Roma Antica',
                ],
                'body' => [
                    'en' => '<p>Rome wasn\'t built in a day, but you can walk its history in one.</p><h2>Morning: The Republic</h2><p>Start at the Roman Forum where senators debated the fate of empires.</p><h2>Midday: The Empire</h2><p>The Colosseum represents Rome at its imperial peak.</p><h2>Afternoon: Early Christianity</h2><p>Visit the Catacombs and early Christian basilicas.</p><h2>Evening: Renaissance & Baroque</h2><p>End at the Trevi Fountain and Spanish Steps.</p>',
                    'es' => '<p>Roma no se construyó en un día.</p><h2>Mañana: La República</h2><p>Comienza en el Foro Romano.</p>',
                    'it' => '<p>Roma non fu costruita in un giorno.</p><h2>Mattina: La Repubblica</h2><p>Inizia al Foro Romano.</p>',
                ],
                'is_featured' => false,
                'published_at' => now()->subDays(20),
            ]
        );

        $this->createBlogPost(
            'italian-food-regional-guide',
            $author1,
            $foodAndWine,
            [
                'title' => [
                    'en' => 'Italian Food by Region: What to Eat Where',
                    'es' => 'Comida Italiana por Región: Qué Comer Dónde',
                    'it' => 'Cucina Italiana per Regione: Cosa Mangiare Dove',
                ],
                'excerpt' => [
                    'en' => 'Italian cuisine varies dramatically from north to south',
                    'es' => 'La cocina italiana varía dramáticamente de norte a sur',
                    'it' => 'La cucina italiana varia drasticamente da nord a sud',
                ],
                'body' => [
                    'en' => '<p>Italy\'s 20 regions each have distinct culinary traditions.</p><h2>Northern Italy</h2><p>Risotto, polenta, and butter-based sauces dominate. Try Milanese osso buco.</p><h2>Central Italy</h2><p>Tuscany: bistecca fiorentina. Rome: carbonara, cacio e pepe, amatriciana.</p><h2>Southern Italy</h2><p>Naples: pizza margherita. Sicily: arancini and cannoli.</p><h2>Wine Pairings</h2><p>Match regional foods with regional wines for authentic experiences.</p>',
                    'es' => '<p>Las 20 regiones de Italia tienen tradiciones culinarias distintas.</p><h2>Norte de Italia</h2><p>Risotto y polenta dominan.</p>',
                    'it' => '<p>Le 20 regioni italiane hanno tradizioni culinarie distinte.</p><h2>Nord Italia</h2><p>Risotto e polenta dominano.</p>',
                ],
                'is_featured' => true,
                'published_at' => now()->subDays(3),
            ]
        );

        $this->command->info('✓ Added blog articles with authors and categories');
    }

    private function createAuthor(string $email, string $name, array $bio): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => 'admin',
                'locale' => 'en',
                'email_verified_at' => now(),
            ]
        );

        AuthorProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => array_fill_keys(BlogPost::LOCALES, $name),
                'bio' => $bio,
                'avatar_url' => null,
            ]
        );

        return $user;
    }

    private function createBlogPost(
        string $slug,
        User $author,
        ?BlogCategory $category,
        array $data
    ): BlogPost {
        if (!$category) {
            $category = BlogCategory::where('slug', 'city-guides')->first();
        }

        return BlogPost::updateOrCreate(
            ['slug' => $slug],
            [
                'author_id' => $author->id,
                'blog_category_id' => $category->id,
                'status' => BlogPost::STATUS_PUBLISHED,
                'title' => $data['title'],
                'excerpt' => $data['excerpt'],
                'body' => $data['body'],
                'is_featured' => $data['is_featured'] ?? false,
                'published_at' => $data['published_at'],
                'cover_image_url' => 'http://cdn.bookly.test/blog/' . $slug . '.jpg',
            ]
        );
    }
}
