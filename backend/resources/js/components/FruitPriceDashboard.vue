<template>
    <div style="padding: 20px; font-family: sans-serif;">
        <h2 style="color: #337ab7;">📊 水果價格預測系統 (Hardcode 預設版)</h2>
        
        <div style="background: #f4f4f4; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 12px;">水果選擇</label>
                <select v-model="filters.crop_id" style="height: 30px; border: 1px solid #ddd; border-radius: 4px;">
                    <option v-for="c in cropList" :key="c.crop_id" :value="c.crop_id">{{ c.crop_name }}</option>
                </select>
            </div>
            
            <div style="display: flex; flex-direction: column;">
                <label style="font-size: 12px;">日期範圍</label>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <input type="date" v-model="filters.start_date" style="border: 1px solid #ddd; padding: 4px; border-radius: 4px;">
                    <span>~</span>
                    <input type="date" v-model="filters.end_date" style="border: 1px solid #ddd; padding: 4px; border-radius: 4px;">
                </div>
            </div>

            <button @click="fetchData" style="padding: 6px 20px; background: #3c8dbc; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
                執行查詢
            </button>
        </div>

        <hr>

        <div v-if="loading">📡 資料傳輸中...</div>
        
        <div v-if="prices.length > 0">
            <h4>搜尋結果：共 {{ prices.length }} 筆</h4>
            <table border="1" style="width: 100%; border-collapse: collapse;">
                <thead style="background: #eee;">
                    <tr>
                        <th style="padding: 10px; text-align: left;">日期</th>
                        <th style="padding: 10px; text-align: left;">作物</th>
                        <th style="padding: 10px; text-align: left;">數據模式</th>
                        <th style="padding: 10px; text-align: right;">價格 (元/公斤)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in prices" :key="index">
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">{{ item["日期"] }}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">{{ item["作物"] }}</td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee;">
                            <span :style="{ color: (item['數據模式'] === 'actual' || item['數據模式'] === '實際值') ? '#3c8dbc' : '#00a65a' }">
                                {{ (item['數據模式'] === 'actual' || item['數據模式'] === '實際值') ? '實際值' : '預測值' }}
                            </span>
                        </td>
                        <td style="padding: 10px; border-bottom: 1px solid #eee; text-align: right; color: #d32f2f; font-weight: bold;">
                            {{ item["價格(元/公斤)"] }} 元
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import axios from "axios";

const prices = ref([]);
const cropList = ref([]);
const loading = ref(false);

// 🔍 修正日期格式化函數，避免 ISO 時區偏移導致輸入框失效
const formatLocalDate = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
};

const now = new Date();
const threeMonthsAgo = new Date();
threeMonthsAgo.setMonth(now.getMonth() - 3);
const sevenDaysLater = new Date();
sevenDaysLater.setDate(now.getDate() + 7);

const filters = reactive({ 
    crop_id: "", 
    start_date: formatLocalDate(threeMonthsAgo), // 今日-3個月
    end_date: formatLocalDate(sevenDaysLater)    // 今日+7天
});

const init = async () => {
    try {
        const res = await axios.get("/api/v1/maintenance/crops");
        cropList.value = res.data;
        const orange = cropList.value.find(c => c.crop_name === '柳橙');
        filters.crop_id = orange ? orange.crop_id : cropList.value[0]?.crop_id;
        fetchData();
    } catch (e) {
        console.error("初始化選單失敗");
    }
};

const fetchData = async () => {
    loading.value = true;
    try {
        const res = await axios.get("/api/v1/maintenance/prices", { params: filters });
        prices.value = res.data.data; // 對齊 JSON data 陣列
    } catch (e) {
        alert("查詢失敗");
    } finally {
        loading.value = false;
    }
};

onMounted(init);
</script>