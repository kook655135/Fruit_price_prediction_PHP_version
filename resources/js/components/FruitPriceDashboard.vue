<template>
    <div style="padding: 20px; font-family: sans-serif;">
        <h2 style="color: #337ab7;">📊 水果價格預測系統 (動態資料庫版)</h2>
        
        <div style="background: #f4f4f4; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
            <div style="display: flex; flex-direction: column;">
                <label>選擇水果</label>
                <select v-model="filters.crop_id" style="padding: 8px;">
                    <option v-for="c in cropList" :key="c.crop_id" :value="c.crop_id">{{ c.crop_name }}</option>
                </select>
            </div>
            <div style="display: flex; flex-direction: column;">
                <label>起始日期</label>
                <input type="date" v-model="filters.start" style="padding: 6px;">
            </div>
            <div style="display: flex; flex-direction: column;">
                <label>結束日期</label>
                <input type="date" v-model="filters.end" style="padding: 6px;">
            </div>
            <button @click="fetchData" style="padding: 8px 20px; background: #337ab7; color: white; border: none; border-radius: 4px; cursor: pointer;">
                執行查詢
            </button>
        </div>

        <hr>

        <div v-if="loading">📡 正在從資料庫提取數據...</div>
        
        <div v-if="prices && prices.length > 0">
            <h4>搜尋結果：共 {{ prices.length }} 筆</h4>
            <table border="1" style="width: 100%; border-collapse: collapse;">
                <thead style="background: #eee;">
                    <tr>
                        <th style="padding: 8px;">日期</th>
                        <th style="padding: 8px;">作物</th>
                        <th style="padding: 8px;">價格 (元/公斤)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in prices" :key="index" style="background: #f9f9f9;">
                        <td style="padding: 8px;">{{ item["日期"] }}</td>
                        <td style="padding: 8px;">{{ item["作物"] }}</td>
                        <td style="padding: 8px; font-weight: bold; color: red;">{{ item["價格(元/公斤)"] }} 元</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div v-else-if="!loading" style="text-align: center; color: #999; margin-top: 20px;">
            請選擇條件並點擊查詢按鈕。
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import axios from "axios";

const prices = ref([]);
const cropList = ref([]);
const loading = ref(false);
const filters = reactive({ 
    crop_id: "", 
    start: "", 
    end: "" 
});

// 載入時自動抓取資料庫的水果清單
const init = async () => {
    try {
        const res = await axios.get("/api/v1/maintenance/crops");
        cropList.value = res.data;
        if(cropList.value.length > 0) {
            filters.crop_id = cropList.value[0].crop_id;
        }
    } catch (e) {
        console.error("選單載入失敗");
    }
};

// 執行價格查詢
const fetchData = async () => {
    loading.value = true;
    try {
        const res = await axios.get("/api/v1/maintenance/prices", {
            params: {
                crop_id: filters.crop_id,
                start_date: filters.start,
                end_date: filters.end
            }
        });
        prices.value = res.data.data;
    } catch (e) {
        alert("查詢失敗");
    } finally {
        loading.value = false;
    }
};

onMounted(init);
</script>
