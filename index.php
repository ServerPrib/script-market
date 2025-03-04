<?php
$config = require 'config.php';
$apiBaseURL = $config['api']['base_url'];
$apiKey = $config['api']['api_key'];
$product = $config['product'];
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembelian <?= htmlspecialchars($product['title']) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'media',
            theme: {
                extend: {
                    fontFamily: {
                        inter: ["Inter", "sans-serif"],
                    },
                    colors: {
                        primary: "#6366F1",
                        secondary: "#818CF8",
                    }
                }
            }
        };
    </script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100 font-inter">

<div class="min-h-screen flex items-center justify-center p-5">
    <div class="max-w-md w-full bg-white dark:bg-gray-800 shadow-lg rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
        <div class="relative">
            <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['title']) ?>" class="w-full">
            <div class="absolute top-2 right-2 bg-green-500 text-white text-xs font-semibold px-3 py-1 rounded-full">
                Best Seller
            </div>
        </div>
        <div class="p-6">
            <h2 class="text-2xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($product['title']) ?></h2>
            <p class="text-gray-600 dark:text-gray-300 mt-2 text-sm"><?= htmlspecialchars($product['description']) ?></p>

            <div class="flex items-center justify-between mt-4">
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">Rp <?= number_format($product['price'], 0, ',', '.') ?></p>
                <button onclick="order()" class="bg-gradient-to-r from-primary to-secondary hover:opacity-90 text-white font-semibold px-5 py-2 rounded-lg transition">
                    <i class="fas fa-shopping-cart"></i> Order
                </button>
            </div>
        </div>
    </div>
</div>

<div id="popup" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
    <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-xl max-w-sm w-full border border-gray-200 dark:border-gray-700">
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white">Pembayaran QRIS</h3>
        <div id="qris-container" class="bg-gray-200 dark:bg-gray-700 p-3 rounded-lg mt-3">
            <img id="qris-image" src="" alt="QRIS" class="w-full rounded-md">
        </div>
        <p id="payment-status" class="text-blue-500 font-semibold mt-2"></p>
            <a id="download-link" href="#" onclick="fetchDownloadLink()" class="block text-center bg-green-500 hover:bg-green-600 text-white font-semibold py-2 rounded-lg hidden">
    <i class="fas fa-download"></i> Download Project
</a>
        <div class="mt-4">
            <p class="text-gray-600 dark:text-gray-300"><strong>Harga:</strong> Rp <?= number_format($product['price'], 0, ',', '.') ?></p>
            <p id="expired-text" class="text-red-500 font-semibold">⚠️ Expired: <span id="expired-date"></span></p>
        </div>
        <div class="flex gap-2 mt-5">
            <button id="cancel-button" onclick="cancelPayment()" class="w-full bg-red-500 hover:bg-red-600 text-white font-semibold py-2 rounded-lg">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button onclick="togglePopup()" class="w-full bg-gray-500 hover:bg-gray-600 text-white font-semibold py-2 rounded-lg">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<script>
    function togglePopup() {
      document.getElementById('popup').classList.toggle('hidden');
    }

    let apiKey = '<?= $apiKey ?>';
    let baseURL = '<?= $apiBaseURL ?>';
    let paymentCheckInterval = null;

    async function order() {
      let paymentData = JSON.parse(localStorage.getItem("paymentData"));
      
      if (localStorage.getItem("payment_success") === "true") {
      	showDownloadPopup();
      } else if (paymentData && paymentData.status === "pending") {
        showPaymentPopup(paymentData);
      } else {
        await createPayment();
      }
    }

    async function createPayment() {
      let reffId = "reff-" + Math.random().toString(36).substring(2, 10);

      try {
        const response = await fetch(baseURL + "/api/h2h/deposit/create", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            "nominal": <?= $product['price'] ?>,
            "method": "QRISFAST",
            "fee_by_customer": "false",
            "reff_id": reffId,
            "api_key": apiKey
          })
        });

        const data = await response.json();
        if (data.status === "success") {
          let paymentData = {
            id: data.data.id,
            reff_id: data.data.reff_id,
            qr_url: data.data.qr_image_url,
            expired_at: data.data.expired_at,
            status: "pending"
          };
          localStorage.setItem("paymentData", JSON.stringify(paymentData));
          showPaymentPopup(paymentData);
          checkPaymentStatus();
        } else {
          alert("Gagal membuat pembayaran!");
        }
      } catch (error) {
        console.error("Error:", error);
        alert("Terjadi kesalahan dalam pembuatan pembayaran.");
      }
    }

    function showPaymentPopup(paymentData) {
      document.getElementById("qris-image").src = paymentData.qr_url;
      document.getElementById("expired-date").textContent = paymentData.expired_at || "Unknown";
      document.getElementById("payment-status").textContent = "Menunggu pembayaran...";

      document.getElementById("qris-container").style.display = "block";
      document.getElementById("cancel-button").style.display = "block";
      document.getElementById("expired-text").style.display = "block";

      togglePopup();
    }
    
    function showDownloadPopup() {
      document.getElementById("download-link").classList.remove("hidden");
      document.getElementById("qris-container").style.display = "none";
      document.getElementById("cancel-button").style.display = "none";
      document.getElementById("expired-text").style.display = "none";

      togglePopup();
    }

    async function checkPaymentStatus() {
      let paymentData = JSON.parse(localStorage.getItem("paymentData"));
      if (!paymentData) return;

      if (paymentCheckInterval) clearInterval(paymentCheckInterval);

      paymentCheckInterval = setInterval(async () => {
        try {
          const response = await fetch(baseURL + "/api/h2h/deposit/status", {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify({
              "id": paymentData.id,
              "api_key": apiKey
            })
          });

          const data = await response.json();
          let status = data.data.status;
          let statusMessage = "";
          let downloadContainer = document.getElementById("download-container");

          if (status === "success") {
          	statusMessage = "✅ Pembayaran berhasil!";
    localStorage.setItem("payment_success", "true");
    document.getElementById("download-link").classList.remove("hidden");
} else if (status === "canceled" || status === "failed") {
            statusMessage = "❌ Pembayaran dibatalkan!";
          } else if (status === "expired") {
            statusMessage = "⚠️ Pembayaran expired!";
          }

          if (["success", "canceled", "failed", "expired"].includes(status)) {
            clearInterval(paymentCheckInterval);
            paymentCheckInterval = null;
            localStorage.removeItem("paymentData");
            document.getElementById("payment-status").textContent = statusMessage;

            document.getElementById("qris-container").style.display = "none";
            document.getElementById("cancel-button").style.display = "none";
            document.getElementById("expired-text").style.display = "none";
          }
        } catch (error) {
          console.error("Error:", error);
        }
      }, 5000);
    }

    async function cancelPayment() {
      let paymentData = JSON.parse(localStorage.getItem("paymentData"));
      if (!paymentData) return alert("Tidak ada pembayaran yang dapat dibatalkan.");

      try {
        await fetch(baseURL + "/api/h2h/deposit/cancel", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            "id": paymentData.id,
            "api_key": apiKey
          })
        });

        localStorage.removeItem("paymentData");
        document.getElementById("payment-status").textContent = "❌ Pembayaran dibatalkan.";

        if (paymentCheckInterval) {
          clearInterval(paymentCheckInterval);
          paymentCheckInterval = null;
        }

        document.getElementById("qris-container").style.display = "none";
        document.getElementById("cancel-button").style.display = "none";
        document.getElementById("expired-text").style.display = "none";
      } catch (error) {
        console.error("Error:", error);
      }
    }
    
    document.addEventListener("DOMContentLoaded", function () {
    if (localStorage.getItem("payment_success") === "true") {
        document.getElementById("download-link").classList.remove("hidden");
    }
});

  async function fetchDownloadLink() {
    if (localStorage.getItem("payment_success") !== "true") {
        alert("Anda harus menyelesaikan pembayaran terlebih dahulu.");
        return;
    }

    try {
        const response = await fetch("download.php", { method: "POST" });
        const data = await response.json();

        if (data.download_url) {
            window.location.href = data.download_url;
        } else {
            alert("Gagal mengambil link download.");
        }
    } catch (error) {
        console.error("Error:", error);
        alert("Terjadi kesalahan.");
    }
}

  </script>
  
</body>
</html>