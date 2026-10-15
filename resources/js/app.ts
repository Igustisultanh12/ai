import { createApp } from 'vue';
import App from './App.vue';
import { router } from './router';
import { installMotion } from './motion';
import Icon from './components/ui/Icon.vue';
import '../css/app.css';

const app = createApp(App);
app.use(router);
app.component('Icon', Icon);
installMotion(app);
app.mount('#app');
