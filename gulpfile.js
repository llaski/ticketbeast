const elixir = require('laravel-elixir');

require('laravel-elixir-vue-2');

elixir(mix => {
    mix.less('app.less')
       .webpack('app.js')
       .version(['css/app.css', 'js/app.js']);
});
