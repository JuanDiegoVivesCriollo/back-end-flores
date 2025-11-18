<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LandingPageContent;

class LandingPageContentSeeder extends Seeder
{
    public function run()
    {
        // Sección Hero
        LandingPageContent::setContent('hero', 'title', 'Flores Djazmin', 'text', 'Título principal del hero');
        LandingPageContent::setContent('hero', 'subtitle', 'Las mejores flores para todas las ocasiones', 'text', 'Subtítulo del hero');
        LandingPageContent::setContent('hero', 'description', 'Descubre nuestra increíble selección de flores frescas, perfectas para expresar tus sentimientos más profundos.', 'text', 'Descripción del hero');
        LandingPageContent::setContent('hero', 'cta_text', 'Ver Catálogo', 'text', 'Texto del botón principal');
        LandingPageContent::setContent('hero', 'background_image', '/img/heroimagen1.webp', 'image', 'Imagen de fondo del hero');

        // Sección About
        LandingPageContent::setContent('about', 'title', 'Sobre Nosotros', 'text', 'Título de la sección sobre nosotros');
        LandingPageContent::setContent('about', 'subtitle', 'Más de 15 años creando momentos especiales', 'text', 'Subtítulo sobre nosotros');
        LandingPageContent::setContent('about', 'description', 'En Flores Djazmin nos especializamos en crear arreglos florales únicos y memorables. Nuestro equipo de expertos floristas selecciona cuidadosamente cada flor para garantizar la máxima frescura y belleza en cada entrega.', 'text', 'Descripción sobre nosotros');

        // Sección Servicios
        LandingPageContent::setContent('services', 'title', 'Nuestros Servicios', 'text', 'Título de servicios');
        LandingPageContent::setContent('services', 'subtitle', 'Todo lo que necesitas para tus ocasiones especiales', 'text', 'Subtítulo de servicios');

        // Sección Contacto
        LandingPageContent::setContent('contact', 'title', 'Contáctanos', 'text', 'Título de contacto');
        LandingPageContent::setContent('contact', 'subtitle', 'Estamos aquí para ayudarte', 'text', 'Subtítulo de contacto');
        LandingPageContent::setContent('contact', 'phone', '+51 999 888 777', 'text', 'Teléfono de contacto');
        LandingPageContent::setContent('contact', 'email', 'info@floresydetalleslima.com', 'text', 'Email de contacto');
        LandingPageContent::setContent('contact', 'address', 'Av. Principal 123, Lima, Perú', 'text', 'Dirección física');
        LandingPageContent::setContent('contact', 'hours', 'Lunes a Sábado: 9:00 AM - 8:00 PM\nDomingo: 10:00 AM - 6:00 PM', 'text', 'Horarios de atención');

        // Configuración general
        LandingPageContent::setContent('general', 'company_name', 'Flores Djazmin', 'text', 'Nombre de la empresa');
        LandingPageContent::setContent('general', 'tagline', 'Creando momentos únicos con flores', 'text', 'Eslogan de la empresa');
        LandingPageContent::setContent('general', 'meta_description', 'Flores Djazmin - Las mejores flores frescas para todas las ocasiones. Entrega a domicilio en Lima.', 'text', 'Descripción meta para SEO');

        echo "Contenido de landing page creado exitosamente.\n";
    }
}
