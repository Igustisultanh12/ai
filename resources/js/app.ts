import { createApp } from 'vue';
import App from './App.vue';
import { router } from './router';
import { installMotion } from './motion';
import '../css/app.css';

const app = createApp(App);
app.use(router);
installMotion(app);
app.mount('#app');
