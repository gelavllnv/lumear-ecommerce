<?php

namespace App\Support;

class PhilippineLocations
{
    public static function regions(): array
    {
        return [
            'NCR' => ['Metro Manila'],
            'Region IV-A (CALABARZON)' => ['Batangas', 'Cavite', 'Laguna', 'Quezon', 'Rizal'],
            'Region VII (Central Visayas)' => ['Cebu'],
        ];
    }

    public static function all(): array
    {
        return [
            'Laguna' => ['Alaminos', 'Bay', 'Biñan', 'Cabuyao', 'Calamba', 'Calauan', 'Cavinti', 'Famy', 'Kalayaan', 'Liliw', 'Los Baños', 'Luisiana', 'Lumban', 'Mabitac', 'Magdalena', 'Majayjay', 'Nagcarlan', 'Paete', 'Pagsanjan', 'Pakil', 'Pangil', 'Pila', 'Rizal', 'San Pablo City', 'San Pedro', 'Santa Cruz', 'Santa Maria', 'Santa Rosa City', 'Siniloan', 'Victoria'],
            'Batangas' => ['Agoncillo', 'Balayan', 'Batangas City', 'Calaca', 'Calatagan', 'Cuenca', 'Lemery', 'Lipa City', 'Mabini', 'Malvar', 'Nasugbu', 'San Jose', 'San Juan', 'Santo Tomas', 'Tanauan City', 'Taal'],
            'Cavite' => ['Alfonso', 'Bacoor', 'Carmona', 'Cavite City', 'Dasmariñas', 'General Trias', 'Imus', 'Indang', 'Kawit', 'Mendez', 'Naic', 'Rosario', 'Silang', 'Tagaytay', 'Tanza', 'Ternate', 'Trece Martires'],
            'Metro Manila' => ['Caloocan', 'Las Piñas', 'Makati', 'Malabon', 'Mandaluyong', 'Manila', 'Marikina', 'Muntinlupa', 'Navotas', 'Parañaque', 'Pasay', 'Pasig', 'Quezon City', 'San Juan', 'Taguig', 'Valenzuela'],
            'Quezon' => ['Atimonan', 'Candelaria', 'Gumaca', 'Infanta', 'Lucena City', 'Lucban', 'Mauban', 'Pagbilao', 'Sariaya', 'Tayabas City', 'Tiaong'],
            'Rizal' => ['Angono', 'Antipolo', 'Baras', 'Binangonan', 'Cainta', 'Cardona', 'Jalajala', 'Morong', 'Pililla', 'Rodriguez', 'San Mateo', 'Tanay', 'Taytay', 'Teresa'],
            'Cebu' => ['Cebu City', 'Lapu-Lapu City', 'Mandaue City', 'Talisay City'],
        ];
    }
}
