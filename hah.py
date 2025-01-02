import io
from datetime import datetime

# Konfigurasi
base_url = "https://ampunhu.xyz/"
url_file = "url.txt"  # Nama file input yang berisi URL
sitemap_file = "sitemap.xml"  # Nama file output untuk sitemap
robots_file = "robots.txt"  # Nama file output untuk robots.txt

# Fungsi untuk membaca URL dari file

def read_urls(file_path):
    with io.open(file_path, "r", encoding="utf-8") as f:
        return [line.strip() for line in f if line.strip()]

# Fungsi untuk menulis sitemap ke satu file dengan format baru

def write_sitemap(urls, file_name):
    content = '<?xml version="1.0" encoding="UTF-8"?>\n'
    content += '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
    for url in urls:
        content += f"    <url>\n"
        content += f"        <loc>{url}</loc>\n"
        content += f"    </url>\n"
    content += "</urlset>\n"

    with io.open(file_name, "w", encoding="utf-8") as f:
        f.write(content)

# Fungsi untuk menulis robots.txt dengan format standar Google

def write_robots_txt(base_url, sitemap_file):
    content = "User-agent: *\n"
    content += "Allow: /\n"
    content += f"Sitemap: {base_url}{sitemap_file}\n"

    with io.open(robots_file, "w", encoding="utf-8") as f:
        f.write(content)

# Membaca URL dari file
all_urls = read_urls(url_file)

# Menulis semua URL ke satu file sitemap dengan format baru
write_sitemap(all_urls, sitemap_file)

# Menulis robots.txt
write_robots_txt(base_url, sitemap_file)

# Output informasi
print("Sitemap dan robots.txt berhasil dibuat!")
print(f"Total URL: {len(all_urls)}")
print(f"Sitemap disimpan di: {sitemap_file}")
print(f"Robots.txt disimpan di: {robots_file}")
