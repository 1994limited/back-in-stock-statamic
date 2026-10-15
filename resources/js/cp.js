import Index from './pages/Index.vue';
import Show from './pages/Show.vue';
import Person from './pages/Person.vue';
import BackInStockWidget from './widgets/BackInStockWidget.vue';

Statamic.booting(() => {
    Statamic.$inertia.register('back-in-stock::Index', Index);
    Statamic.$inertia.register('back-in-stock::Show', Show);
    Statamic.$inertia.register('back-in-stock::Person', Person);
    Statamic.$components.register('back-in-stock-widget', BackInStockWidget);
});
