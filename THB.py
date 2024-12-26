import nest_asyncio
import asyncio
import locale
from datetime import datetime
from io import BytesIO
import matplotlib.pyplot as plt
import requests
from telegram import Update, InlineKeyboardMarkup, InlineKeyboardButton
from telegram.ext import ApplicationBuilder, CommandHandler, MessageHandler, CallbackQueryHandler, filters, ContextTypes
import numpy as np

# Menggunakan nest_asyncio agar asyncio berjalan di lingkungan interaktif
nest_asyncio.apply()

# Mengatur locale untuk format angka ke Indonesia
locale.setlocale(locale.LC_ALL, 'id_ID.UTF-8')

# API Key OpenWeatherMap
OPENWEATHER_API_KEY = "5b41550f54c1b519a95454528acc7802"  # Ganti dengan API key Anda
BOT_TOKEN = "7548323996:AAG7KMn4Bg8VeVkdtkJmYXhFeNOVvOH3T24"  # Ganti dengan token bot Anda

# Fungsi untuk mendapatkan data cuaca
def get_weather(city, units='metric'):
    url = f"http://api.openweathermap.org/data/2.5/weather?q={city}&appid={OPENWEATHER_API_KEY}&units={units}"
    response = requests.get(url)
    if response.status_code == 200:
        data = response.json()
        return data['main']['temp'], data['weather'][0]['description']
    else:
        return None, f"Kota {city} tidak ditemukan."

# Fungsi untuk mencatat transaksi
def add_financial_record(user_data, amount, transaction_type, category):
    if 'transactions' not in user_data:
        user_data['transactions'] = []
    
    # Mendapatkan tanggal dan waktu transaksi
    transaction_date = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    # Menambahkan transaksi dengan informasi lengkap
    user_data['transactions'].append({
        'amount': amount,
        'type': transaction_type,  # 'masuk' atau 'keluar'
        'category': category,
        'date': transaction_date
    })

# Fungsi untuk menghitung laporan keuangan
def calculate_profit_loss(user_data, category_filter=None):
    masuk = sum(record['amount'] for record in user_data.get('transactions', [])
                 if record['type'] == 'masuk' and (category_filter is None or record['category'] == category_filter))
    keluar = sum(record['amount'] for record in user_data.get('transactions', [])
                  if record['type'] == 'keluar' and (category_filter is None or record['category'] == category_filter))
    return masuk, keluar, masuk - keluar

# Fungsi untuk membuat grafik pie chart pengeluaran dengan tampilan lebih profesional dan tanda panah
def generate_keluar_chart(user_data):
    categories = {}
    for record in user_data.get('transactions', []):
        if record['type'] == 'keluar':
            category = record['category']
            categories[category] = categories.get(category, 0) + record['amount']

    if not categories:
        return None

    # Menentukan warna yang lebih menarik
    colors = plt.cm.Paired.colors[:len(categories)]

    # Membuat figure dan axis untuk grafik dengan latar belakang hitam
    fig, ax = plt.subplots(figsize=(8, 6))
    fig.patch.set_facecolor('black')  # Latar belakang grafik
    ax.set_facecolor('black')  # Latar belakang area grafik

    # Membuat grafik pie dengan tampilan yang lebih rapi dan desain modern
    wedges, texts, autotexts = ax.pie(
        categories.values(), 
        labels=categories.keys(),
        autopct='%1.1f%%',  # Persentase dengan satu digit di belakang koma
        startangle=90,
        colors=colors,
        wedgeprops={'edgecolor': 'white', 'linewidth': 1.5},  # Membuat garis tepi lebih jelas
        textprops={'color': 'white', 'fontsize': 14, 'fontweight': 'bold'},  # Teks label putih dan tebal
        pctdistance=0.85,  # Jarak persentase dari tengah
    )

    # Menambahkan judul yang lebih menarik dengan teks putih
    ax.set_title('Distribusi Pengeluaran per Kategori', fontsize=18, fontweight='bold', color='white')

    # Menambahkan label untuk autotexts dengan warna yang kontras
    for autotext in autotexts:
        autotext.set(color='white', fontsize=14, fontweight='bold')

    ax.axis('equal')  # Menjaga pie chart tetap bulat

    # Menambahkan tanda panah untuk kategori tertentu
    # Menambahkan panah ke kategori pertama (misalnya kategori terbesar atau yang diinginkan)
    largest_wedge = wedges[0]  # Mengambil wedge pertama, bisa diganti untuk kategori lain
    angle = (largest_wedge.theta2 + largest_wedge.theta1) / 2  # Mengambil posisi tengah wedge
    x = 1.1 * (0.5 * (largest_wedge.r + largest_wedge.r)) * np.cos(np.radians(angle))  # Koordinat x
    y = 1.1 * (0.5 * (largest_wedge.r + largest_wedge.r)) * np.sin(np.radians(angle))  # Koordinat y

    # Menambahkan anotasi panah ke kategori terbesar
    ax.annotate(
        'Kategori Terbesar',  # Teks annotation
        xy=(x, y),  # Posisi koordinat panah
        xytext=(1.3 * x, 1.3 * y),  # Posisi teks annotation
        arrowprops=dict(arrowstyle="->", color="white", lw=2),  # Gaya panah
        fontsize=14,  # Ukuran font untuk teks
        color='white',  # Warna teks
        fontweight='bold'
    )

    # Mengatur margin agar lebih rapi
    plt.subplots_adjust(left=0.1, right=0.9, top=0.9, bottom=0.1)

    # Menyimpan grafik ke buffer dan mengembalikannya
    buf = BytesIO()
    plt.savefig(buf, format="png", bbox_inches='tight')
    buf.seek(0)
    return buf

# Fungsi format angka ke mata uang
def format_currency(value):
    return locale.format_string("%d", value, grouping=True)

# Perintah bot
async def start(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await update.message.reply_text("Halo! Saya adalah bot keuangan dan cuaca. Gunakan /help untuk melihat daftar perintah.")

async def help_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    help_text = (
        "Daftar Perintah yang Tersedia:\n"
        "/start - Mulai bot\n"
        "/help - Lihat daftar perintah\n"
        "/info - Informasi tentang bot\n"
        "/cuaca - Dapatkan cuaca terkini\n"
        "/uang - Catat transaksi keuangan\n"
        "/report - Lihat laporan keuangan\n"
        "/setbudget - Atur anggaran\n"
        "/checkbudget - Periksa anggaran\n"
        "/grafik - Tampilkan grafik pengeluaran\n"
    )
    await update.message.reply_text(help_text)

async def info_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    await update.message.reply_text("Bot ini membantu mencatat keuangan Anda dan memberikan informasi cuaca terkini.")

async def cuaca_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if len(context.args) == 0:
        await update.message.reply_text("Gunakan /cuaca [nama_kota]. Contoh: /cuaca Jakarta")
        return

    city = " ".join(context.args)
    temp, description = get_weather(city)
    if temp is not None:
        await update.message.reply_text(f"Cuaca di {city}: {temp}°C, {description}")
    else:
        await update.message.reply_text(description)

async def uang_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if len(context.args) < 3:
        await update.message.reply_text("Format salah! Gunakan /uang [masuk/keluar] [kategori] [jumlah].")
        return

    transaction_type = context.args[0].lower()
    category = context.args[1].lower()
    try:
        amount = float(context.args[2])
    except ValueError:
        await update.message.reply_text("Jumlah harus berupa angka.")
        return

    if transaction_type not in ['masuk', 'keluar']:
        await update.message.reply_text("Tipe transaksi hanya 'masuk' atau 'keluar'.")
        return

    # Catat transaksi keuangan
    add_financial_record(context.user_data, amount, transaction_type, category)
    
    # Jika transaksi adalah pengeluaran, kurangi anggaran jika ada
    if transaction_type == 'keluar' and 'budgets' in context.user_data and category in context.user_data['budgets']:
        budget = context.user_data['budgets'][category]
        if budget >= amount:
            context.user_data['budgets'][category] -= amount
            await update.message.reply_text(f"Transaksi {transaction_type} kategori '{category}' sebesar Thb {format_currency(amount)} dicatat. Sisa anggaran untuk '{category}': Thb {format_currency(context.user_data['budgets'][category])}.")
        else:
            await update.message.reply_text(f"Anggaran untuk kategori '{category}' tidak cukup untuk pengeluaran sebesar Thb {format_currency(amount)}.")
    else:
        await update.message.reply_text(f"Transaksi {transaction_type} kategori '{category}' sebesar Thb {format_currency(amount)} dicatat.")

async def report_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    # Mendapatkan tanggal dan waktu saat laporan dibuat
    report_date = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    # Menyaring berdasarkan kategori jika ada
    category_filter = None
    if len(context.args) > 0:
        category_filter = context.args[0].lower()

    # Mengambil total pendapatan dan pengeluaran
    masuk, keluar, profit_loss = calculate_profit_loss(context.user_data, category_filter)

    # Membuat laporan pendapatan dan pengeluaran
    report = (
        f"Laporan Keuangan per {report_date}:\n"
        f"Total Pendapatan: Thb {format_currency(masuk)}\n"
        f"Total Pengeluaran: Thb {format_currency(keluar)}\n"
        f"Sisa: Thb {format_currency(profit_loss)}\n\n"
        "Detail Transaksi:\n"
    )

    # Menampilkan riwayat transaksi dengan kategori, jumlah, dan waktu
    if 'transactions' in context.user_data:
        for record in context.user_data['transactions']:
            transaction_time = record['date']
            transaction_type = record['type'].capitalize()
            category = record['category'].capitalize()
            amount = format_currency(record['amount'])
            
            report += (f"{transaction_time} - {transaction_type} - {category}: Thb {amount}\n")
    else:
        report += "Tidak ada transaksi yang tercatat."

    # Mengirim laporan transaksi ke pengguna
    await update.message.reply_text(report)

async def show_keluar_chart(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    chart = generate_keluar_chart(context.user_data)
    if chart:
        await update.message.reply_photo(chart)
    else:
        await update.message.reply_text("Tidak ada pengeluaran untuk ditampilkan.")

# Fungsi untuk mengatur anggaran
async def set_budget_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if len(context.args) < 2:
        await update.message.reply_text("Format salah! Gunakan /setbudget [kategori] [jumlah]. Contoh: /setbudget makanan 500000")
        return

    category = context.args[0].lower()
    try:
        amount = float(context.args[1])
    except ValueError:
        await update.message.reply_text("Jumlah harus berupa angka.")
        return

    if 'budgets' not in context.user_data:
        context.user_data['budgets'] = {}
    
    context.user_data['budgets'][category] = amount
    await update.message.reply_text(f"Anggaran untuk kategori '{category}' telah diset sebesar Thb {format_currency(amount)}.")

# Fungsi untuk memeriksa anggaran
async def check_budget_command(update: Update, context: ContextTypes.DEFAULT_TYPE) -> None:
    if 'budgets' not in context.user_data:
        await update.message.reply_text("Tidak ada anggaran yang diset.")
        return

    # Mendapatkan tanggal dan jam saat pengecekan anggaran
    check_date = datetime.now().strftime('%Y-%m-%d %H:%M:%S')

    # Menampilkan anggaran yang telah diset
    budget_report = f"Pengecekan Anggaran per {check_date}:\n"
    for category, amount in context.user_data['budgets'].items():
        budget_report += f"{category.capitalize()}: Thb {format_currency(amount)}\n"
    
    await update.message.reply_text(budget_report)

# Menjalankan bot
if __name__ == '__main__':
    application = ApplicationBuilder().token("8024384473:AAFzwdo7AgkBxWmvGGKCRrgGCwQ4ET711jg").build()

    application.add_handler(CommandHandler("start", start))
    application.add_handler(CommandHandler("help", help_command))
    application.add_handler(CommandHandler("info", info_command))
    application.add_handler(CommandHandler("cuaca", cuaca_command))
    application.add_handler(CommandHandler("uang", uang_command))
    application.add_handler(CommandHandler("report", report_command))
    application.add_handler(CommandHandler("grafik", show_keluar_chart))
    application.add_handler(CommandHandler("setbudget", set_budget_command))
    application.add_handler(CommandHandler("checkbudget", check_budget_command))

    application.run_polling()
