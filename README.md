# 🍎 水果價格預測系統 - 全棧部屬實作案

本專案為因應企業技術挑戰，於 **12 天內從零自學 PHP** 並完成開發之實作案。專案整合了 MySQL 資料庫、RESTful API 開發、以及前端數據監控儀表板，並部屬至 GCP (Google Cloud Platform) 雲端環境。

---

## ⚠️ 雲端資源使用說明 (FinOps)
為落實 GCP 雲端預算控管與資源自動化管理，本系統實施排程運行：
- **服務開放時間：** 每日 **07:00 ~ 22:00** (Asia/Taipei)。
- **其餘時段：** 系統將進入休眠狀態以節省運算資源。

---

## 📊 視覺化監控儀表板 (Live Demo)
為了直觀呈現數據分析與預測結果，本系統提供動態儀表板，支援即時價格趨勢追蹤與跨縣市產量分佈分析。

- **🔗 展示連結：** [水果價格預測系統儀表板](http://35.201.244.184:8081/)
- **登入帳號：** `admin`
- **登入密碼：** `admin`
- **核心功能：** 支援多種作物趨勢切換、平均價格監控、全台產量分佈地圖。

---

## 🛠️ 技術規格 (Backend API)
系統提供一套完整的 RESTful API 接口，支援以 **作物名稱 (crop_name)** 直接進行數據管理，無需處理繁瑣的 ID 轉換。

- **Base URL:** `http://35.201.244.184:8081/api/v1/maintenance`
- **回應格式:** `application/json` (UTF-8)

---

## 📡 API 接口總覽 (CRUD)

| 功能 | Method | Endpoint | 關鍵參數 | 說明 |
| :--- | :--- | :--- | :--- | :--- |
| **查詢 (Read)** | `GET` | `/prices` | `crop_names[]`, `start_date`, `end_date` | 取得指定範圍內的價格數據 (建議測試 2025 年資料) |
| **新增 (Create)** | `POST` | `/prices` | `date`, `crop_name`, `price`, `mode` | 插入新紀錄 (測試請用 2099 年避免污染數據) |
| **更新 (Update)** | `PUT` | `/prices` | `date`, `crop_name`, `mode`, `price` | 基於複合鍵更新指定紀錄的價格 |
| **刪除 (Delete)** | `DELETE` | `/prices` | `date`, `crop_name`, `mode` | 基於複合鍵移除特定數據 |

---

## 📝 呼叫範例

### 1. 數據查詢 (使用 2025 現有資料範例)
為了確保能獲取真實資料，查詢建議以 2025 年為範圍。
您可以直接將下方完整網址複製到 Chrome 瀏覽器，或直接點擊連結查看結果：

- **完整測試連結：** [點我直接執行 GET 查詢](http://35.201.244.184:8081/api/v1/maintenance/prices?crop_names[]=柳橙&start_date=2025-01-01&end_date=2025-12-31)
- **請求格式：** `Base URL` + `/prices` + `參數`

### 2. 數據維護 (使用 2099 測試日期範例)
為了避免汙染現有資料庫，**新增、更新、刪除** 操作請統一使用 `2099-01-03` 進行測試：

- **新增數據 (POST):**
```json
{
  "date": "2099-01-03",
  "crop_name": "柳橙",
  "price": 45.5,
  "mode": "prediction"
}
```

- **更新數據 (PUT):**
```json
{
  "date": "2099-01-03",
  "crop_name": "柳橙",
  "mode": "prediction",
  "price": 42.8
}
```

- **刪除數據 (DELETE)**
```json
{
  "date": "2099-01-03",
  "crop_name": "柳橙",
  "mode": "prediction"
}