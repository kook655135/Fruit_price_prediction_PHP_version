import './bootstrap';
import { createApp } from 'vue';
import FruitPriceDashboard from './components/FruitPriceDashboard.vue';

const app = createApp({});
app.component('fruit-price-dashboard', FruitPriceDashboard);
app.mount('#app'); // 將 Vue 掛載到 id 為 app 的 HTML 標籤上