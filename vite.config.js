import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            /*
             * Fontlar derleme anında indirilip kendi sunucumuzdan servis edilir;
             * çalışma anında dış istek yok. Rubik: RealHomes Modern 5 referansının
             * yazısı, Türkçe karakterleri tam destekler.
             */
            fonts: [
                bunny('Rubik', { weights: [400, 500, 600, 700] }),
            ],
        }),
    ],
    server: {
        watch: { ignored: ['**/storage/framework/views/**'] },
    },
});
