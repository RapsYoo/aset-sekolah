import tkinter as tk

import time
import glob
import tkinter as tk
from tkinter import ttk, messagebox
from tkcalendar import DateEntry
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select, WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.keys import Keys
import os
import sys
import json
import requests
from urllib.parse import urljoin

# ===================== HELPER FUNCTION UNTUK DOWNLOAD =====================
def download_excel_helper(driver, unit_input, school_name, download_dir):
    """
    Helper function untuk download file Excel dengan bypass Chrome Safe Browsing.
    Menggunakan network interceptor CDP untuk menangkap URL download.
    """
    import urllib3
    urllib3.disable_warnings(urllib3.exceptions.InsecureRequestWarning)
    
    unit_input.clear()
    unit_input.send_keys(school_name)
    time.sleep(2)
    
    try:
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")))
        school_row = driver.find_element(By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")
        school_row.click()
        time.sleep(2)
        
        # Ambil cookies dari Selenium untuk requests session
        selenium_cookies = driver.get_cookies()
        session = requests.Session()
        for cookie in selenium_cookies:
            session.cookies.set(cookie['name'], cookie['value'])
        
        # Dapatkan URL halaman saat ini
        current_url = driver.current_url
        
        # Set headers seperti browser
        headers = {
            'User-Agent': driver.execute_script("return navigator.userAgent;"),
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language': 'en-US,en;q=0.5',
            'Referer': current_url,
            'Connection': 'keep-alive',
        }
        
        # Enable network monitoring untuk capture download URL
        driver.execute_cdp_cmd('Network.enable', {})
        
        # Clear performance logs
        driver.execute_script("window.performance.clearResourceTimings();")
        
        # Simpan nama file untuk nanti
        safe_school_name = "".join(c for c in school_name if c not in '\\/:*?"<>|')
        file_path = os.path.join(download_dir, f"{safe_school_name}.xls")
        
        # Klik tombol Excel
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "span.l-btn-text.icon-excel")))
        excel_btn = driver.find_element(By.CSS_SELECTOR, "span.l-btn-text.icon-excel")
        
        # Klik tombol
        excel_btn.click()
        time.sleep(3)
        
        # Coba ambil URL dari performance entries (resource timing)
        download_url = None
        try:
            resources = driver.execute_script("""
                return window.performance.getEntriesByType('resource')
                    .filter(r => r.name.includes('cetak') || r.name.includes('excel') || r.name.includes('laporan') || r.name.includes('.xls'))
                    .map(r => r.name);
            """)
            if resources:
                download_url = resources[-1]
                print(f"Found download URL dari performance: {download_url}")
        except Exception as e:
            print(f"Gagal ambil performance entries: {e}")
        
        # Jika tidak dapat URL dari performance, coba dari network logs
        if not download_url:
            try:
                logs = driver.get_log('performance')
                for log in reversed(logs):
                    message = json.loads(log['message'])
                    if 'Network.responseReceived' in str(message):
                        url = message.get('message', {}).get('params', {}).get('response', {}).get('url', '')
                        if 'excel' in url.lower() or 'xls' in url.lower() or 'cetak' in url.lower():
                            download_url = url
                            print(f"Found download URL dari network log: {download_url}")
                            break
            except Exception as e:
                print(f"Gagal ambil network logs: {e}")
        
        # Jika dapat download URL, download via requests
        if download_url:
            try:
                response = session.get(
                    download_url,
                    headers=headers,
                    verify=False,
                    allow_redirects=True,
                    timeout=60
                )
                
                if response.status_code == 200:
                    # Cek content type atau ukuran file
                    content_type = response.headers.get('Content-Type', '').lower()
                    
                    if len(response.content) > 500:  # File harus lebih dari 500 bytes
                        with open(file_path, 'wb') as f:
                            f.write(response.content)
                        print(f"Downloaded (via requests GET): {file_path}")
                        return True
            except Exception as e:
                print(f"Gagal download via requests: {e}")
        
        # Fallback: Coba POST ke URL yang terlihat seperti endpoint cetak
        print("Mencoba POST ke endpoint cetak...")
        base_url = current_url.rsplit('/', 1)[0]
        
        # Coba beberapa kemungkinan endpoint
        possible_endpoints = [
            current_url.replace('cetak_lap_kib_a', 'excel_kib_a'),
            current_url + '&cetak=excel',
            base_url + '/excel_kib_a',
        ]
        
        for endpoint in possible_endpoints:
            try:
                response = session.get(
                    endpoint,
                    headers=headers,
                    verify=False,
                    allow_redirects=True,
                    timeout=30
                )
                
                if response.status_code == 200 and len(response.content) > 500:
                    # Cek magic bytes untuk Excel
                    if response.content[:8] == b'\xd0\xcf\x11\xe0\xa1\xb1\x1a\xe1' or response.content[:4] == b'PK\x03\x04':
                        with open(file_path, 'wb') as f:
                            f.write(response.content)
                        print(f"Downloaded (via endpoint guessing): {file_path}")
                        return True
            except:
                continue
        
        # Fallback terakhir: Tunggu Chrome download
        print("Fallback: Menunggu Chrome download file...")
        files_before = set(glob.glob(os.path.join(download_dir, "*.xls")))
        
        max_wait = 30
        waited = 0
        downloaded_file = None
        
        while waited < max_wait:
            crdownload_files = glob.glob(os.path.join(download_dir, "*.crdownload"))
            tmp_files = glob.glob(os.path.join(download_dir, "*.tmp"))
            
            if not crdownload_files and not tmp_files:
                files_after = set(glob.glob(os.path.join(download_dir, "*.xls")))
                new_files = files_after - files_before
                if new_files:
                    time.sleep(1)
                    downloaded_file = max(new_files, key=os.path.getctime)
                    break
            
            time.sleep(1)
            waited += 1
            
            if waited % 10 == 0:
                print(f"Menunggu download {school_name}... ({waited}s)")
        
        if downloaded_file:
            if downloaded_file != file_path:
                if os.path.exists(file_path):
                    os.remove(file_path)
                os.rename(downloaded_file, file_path)
            print(f"Downloaded (via Chrome): {file_path}")
            return True
        else:
            print(f"WARNING: Tidak dapat download file untuk {school_name}")
            print("Chrome Safe Browsing masih memblokir download.")
            print("SARAN: Coba nonaktifkan Safe Browsing di Chrome settings:")
            print("  chrome://settings/security -> pilih 'No protection'")
            return False
            
    except Exception as e:
        print(f"Error download {school_name}: {e}")
        import traceback
        traceback.print_exc()
        return False


CLI_MODE = False
CLI_KIB = ''
CLI_ARGS = {}
if len(sys.argv) >= 3 and sys.argv[1] == 'cli':
    CLI_MODE = True
    CLI_KIB = (sys.argv[2] or '').upper()
    CLI_ARGS['tgl_box'] = sys.argv[3] if len(sys.argv) > 3 else ''
    CLI_ARGS['unit_name'] = sys.argv[4] if len(sys.argv) > 4 else ''
    CLI_ARGS['jenis_aset'] = sys.argv[5] if len(sys.argv) > 5 else '1'
    CLI_ARGS['jenis_kode'] = sys.argv[6] if len(sys.argv) > 6 else '1'
    CLI_ARGS['download_dir'] = sys.argv[7] if len(sys.argv) > 7 else ''
    CLI_ARGS['job_dir'] = sys.argv[8] if len(sys.argv) > 8 else ''
    if CLI_ARGS['download_dir']:
        os.environ['KIB_DOWNLOAD_DIR'] = CLI_ARGS['download_dir']
    if CLI_ARGS['job_dir']:
        os.environ['KIB_JOB_DIR'] = CLI_ARGS['job_dir']


# ===================== KIB A =====================
def run_kib_a():
    def login(driver):
        driver.get("https://simbakda.sulselprov.go.id/index.php/welcome/login")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "username")))
        driver.find_element(By.NAME, "username").send_keys("DISDIK2023")
        driver.find_element(By.NAME, "password").send_keys("RAHMATGANTENG")
        Select(driver.find_element(By.NAME, "ta")).select_by_visible_text("2025")
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "button[type='submit']")))
        driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()

    def navigate_to_report(driver):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Laporan-Laporan")))
        laporan_menu = driver.find_element(By.LINK_TEXT, "Laporan-Laporan")
        ActionChains(driver).move_to_element(laporan_menu).click(laporan_menu).perform()
        WebDriverWait(driver, 5).until(EC.presence_of_element_located((By.LINK_TEXT, "Inventaris Barang")))
        driver.find_element(By.LINK_TEXT, "Inventaris Barang").click()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Aset Tetap")))
        aset_tetap_menu = driver.find_element(By.LINK_TEXT, "Aset Tetap")
        ActionChains(driver).move_to_element(aset_tetap_menu).click(aset_tetap_menu).perform()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Kib At")))
        kib_at_menu = driver.find_element(By.LINK_TEXT, "Kib At")
        ActionChains(driver).move_to_element(kib_at_menu).click(kib_at_menu).perform()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Tanah")))
        tanah_menu = driver.find_element(By.LINK_TEXT, "Tanah")
        ActionChains(driver).move_to_element(tanah_menu).click(tanah_menu).perform()

    def isi_form(driver, tgl_value, jenis_aset):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text")))
        input_box = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text")
        input_box.clear()
        input_box.send_keys("1.01.01.00")
        time.sleep(1)
        input_box.send_keys("\ue007")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")))
        tgl_box_elem = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")
        tgl_box_elem.clear()
        tgl_box_elem.send_keys(tgl_value)
        tgl_box_elem.send_keys(Keys.ESCAPE)
        aset_map = {
            "1": "jenis_aset_1",
            "2": "jenis_aset_2",
            "3": "jenis_aset_3"
        }
        aset_id = aset_map.get(jenis_aset, "jenis_aset_1")
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.ID, aset_id)))
        jenis_aset_radio = driver.find_element(By.ID, aset_id)
        driver.execute_script("arguments[0].scrollIntoView({block:'center'});", jenis_aset_radio)
        if not jenis_aset_radio.is_selected():
            driver.execute_script("arguments[0].click();", jenis_aset_radio)

    def get_school_names(driver, unit_name):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, "//td[contains(.,'UNIT')]/following-sibling::td//input[contains(@class,'combo-text') and @autocomplete='off']")))
        unit_input = driver.find_element(By.XPATH, "//td[contains(.,'UNIT')]/following-sibling::td//input[contains(@class,'combo-text') and @autocomplete='off']")
        unit_input.clear()
        unit_input.send_keys(unit_name)
        time.sleep(2)
        school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row') and .//div[contains(@field,'nm_uskpd')]]")
        if not school_rows:
            school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row')]")
        school_names = []
        for row in school_rows:
            try:
                cells = row.find_elements(By.XPATH, ".//div[contains(@class,'datagrid-cell')]")
                if len(cells) >= 2:
                    name = cells[1].text
                    if name:
                        school_names.append(name)
            except:
                pass
        return school_names, unit_input

    def download_excel(driver, unit_input, school_name, download_dir):
        unit_input.clear()
        unit_input.send_keys(school_name)
        time.sleep(2)
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")))
        school_row = driver.find_element(By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")
        school_row.click()
        time.sleep(2)
        
        # Ambil cookies dari Selenium untuk digunakan requests
        selenium_cookies = driver.get_cookies()
        session = requests.Session()
        for cookie in selenium_cookies:
            session.cookies.set(cookie['name'], cookie['value'], domain=cookie.get('domain', ''))
        
        # Klik tombol Excel dan intercept URL download
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "span.l-btn-text.icon-excel")))
        excel_btn = driver.find_element(By.CSS_SELECTOR, "span.l-btn-text.icon-excel")
        
        # Dapatkan parent anchor/button yang memiliki onclick
        parent_element = excel_btn.find_element(By.XPATH, "./..")
        onclick_attr = parent_element.get_attribute("onclick") or ""
        
        # Coba dapatkan URL dari form action atau onclick
        try:
            # Cek apakah ada form yang akan disubmit
            current_url = driver.current_url
            base_url = current_url.rsplit('/', 1)[0]
            
            # Klik tombol Excel untuk trigger download
            excel_btn.click()
            time.sleep(3)
            
            # Cek apakah ada window baru atau download dimulai
            # Jika ada popup/window baru, ambil URL-nya
            windows = driver.window_handles
            if len(windows) > 1:
                driver.switch_to.window(windows[-1])
                download_url = driver.current_url
                driver.close()
                driver.switch_to.window(windows[0])
                
                # Download menggunakan requests
                response = session.get(download_url, allow_redirects=True, verify=False)
                if response.status_code == 200:
                    safe_school_name = "".join(c for c in school_name if c not in '\/:*?"<>|')
                    file_path = os.path.join(download_dir, f"{safe_school_name}.xls")
                    with open(file_path, 'wb') as f:
                        f.write(response.content)
                    print(f"Downloaded: {file_path}")
                    return
            
            # Jika tidak ada window baru, tunggu file muncul di folder download
            files_before = set(glob.glob(os.path.join(download_dir, "*.xls")))
            max_wait = 60
            waited = 0
            downloaded_file = None
            while waited < max_wait:
                files_after = set(glob.glob(os.path.join(download_dir, "*.xls")))
                new_files = files_after - files_before
                if new_files:
                    # Pastikan file sudah selesai download (tidak ada .crdownload)
                    crdownload_files = glob.glob(os.path.join(download_dir, "*.crdownload"))
                    if not crdownload_files:
                        downloaded_file = max(new_files, key=os.path.getctime)
                        break
                time.sleep(1)
                waited += 1

            if downloaded_file:
                safe_school_name = "".join(c for c in school_name if c not in '\/:*?"<>|')
                new_name = f"{safe_school_name}.xls"
                new_path = os.path.join(download_dir, new_name)
                if downloaded_file != new_path:
                    if os.path.exists(new_path):
                        os.remove(new_path)
                    os.rename(downloaded_file, new_path)
                print(f"Downloaded: {new_path}")
            else:
                print(f"Warning: Tidak dapat download file untuk {school_name}")
                
        except Exception as e:
            print(f"Error download {school_name}: {e}")

    def get_user_input():
        if CLI_MODE:
            return CLI_ARGS.get('tgl_box',''), CLI_ARGS.get('unit_name',''), CLI_ARGS.get('jenis_aset','1')
        root = tk.Tk()
        root.title("CETAK LAPORAN KIB A")
        root.geometry("300x200")
        tk.Label(root, text="Masukkan Nama Unit:").pack(pady=(10,0))
        unit_entry = tk.Entry(root)
        unit_entry.pack()
        tk.Label(root, text="KIB Per Tanggal:").pack(pady=(10,0))
        cal = DateEntry(root, date_pattern='yyyy-mm-dd')
        cal.pack()
        tk.Label(root, text="Jenis Aset:").pack(pady=(10,0))
        aset_options = ["Semua", "Intrakomptabel", "Ekstrakomptabel"]
        aset_values = {"Semua": "1", "Intrakomptabel": "2", "Ekstrakomptabel": "3"}
        aset_combo = ttk.Combobox(root, values=aset_options, state="readonly")
        aset_combo.current(0)
        aset_combo.pack()
        result = {}
        submit_btn = tk.Button(root, text="Download")
        def validate(*args):
            if not unit_entry.get().strip():
                submit_btn.config(state="disabled")
            else:
                submit_btn.config(state="normal")
        def submit():
            if not unit_entry.get().strip():
                tk.messagebox.showerror("Error", "Nama Unit tidak boleh kosong!")
                return
            result['tgl_box'] = cal.get()
            result['unit_name'] = unit_entry.get()
            result['jenis_aset'] = aset_values.get(aset_combo.get(), "1")
            root.destroy()
        submit_btn.config(command=submit)
        submit_btn.pack(pady=15)
        submit_btn.config(state="disabled")
        unit_entry.bind("<KeyRelease>", validate)
        root.mainloop()
        return result['tgl_box'], result['unit_name'], result['jenis_aset']

    tgl_box, unit_name, jenis_aset = get_user_input()
    chrome_options = Options()
    
    # Tentukan folder download
    download_dir = os.environ.get("KIB_DOWNLOAD_DIR") or os.path.join(os.path.expanduser("~"), "Downloads")
    
    # Konfigurasi preferences untuk download otomatis tanpa prompt
    prefs = {
        "download.default_directory": download_dir,
        "download.prompt_for_download": False,
        "download.directory_upgrade": True,
        "safebrowsing.enabled": False,
        "safebrowsing.disable_download_protection": True,
        "profile.default_content_setting_values.automatic_downloads": 1,
        "profile.content_settings.exceptions.automatic_downloads.*.setting": 1,
        # Tambahan untuk disable semua proteksi download
        "download.extensions_to_open": "xls,xlsx",
        "profile.default_content_settings.popups": 0,
        "safebrowsing.disable_extension_blacklist": True,
    }
    chrome_options.add_experimental_option("prefs", prefs)
    chrome_options.add_experimental_option("excludeSwitches", ["enable-logging", "enable-automation"])
    chrome_options.add_experimental_option("useAutomationExtension", False)
    
    # Opsi tambahan untuk mengizinkan download - LEBIH AGRESIF
    chrome_options.add_argument('--no-sandbox')
    chrome_options.add_argument('--disable-dev-shm-usage')
    chrome_options.add_argument('--disable-gpu')
    chrome_options.add_argument('--disable-web-security')
    chrome_options.add_argument('--allow-running-insecure-content')
    chrome_options.add_argument('--disable-features=IsolateOrigins,site-per-process,DownloadBubble,DownloadBubbleV2,SafeBrowsingEnhancedProtection')
    chrome_options.add_argument('--disable-site-isolation-trials')
    chrome_options.add_argument('--ignore-certificate-errors')
    chrome_options.add_argument('--ignore-ssl-errors')
    chrome_options.add_argument('--disable-extensions')
    chrome_options.add_argument('--disable-popup-blocking')
    # Disable Safe Browsing sepenuhnya
    chrome_options.add_argument('--safebrowsing-disable-download-protection')
    chrome_options.add_argument('--safebrowsing-disable-extension-blacklist')
    # chrome_options.add_argument('--headless')  # Dinonaktifkan sementara untuk debugging
    chrome_options.add_argument('--window-size=1920,1080')
    
    driver = webdriver.Chrome(options=chrome_options)
    
    # Gunakan CDP untuk mengizinkan download - VERSI MODERN
    try:
        # Metode 1: Browser.setDownloadBehavior (Chrome modern)
        driver.execute_cdp_cmd("Browser.setDownloadBehavior", {
            "behavior": "allow",
            "downloadPath": download_dir,
            "eventsEnabled": True
        })
    except:
        pass
    
    try:
        # Metode 2: Page.setDownloadBehavior (backup)
        driver.execute_cdp_cmd("Page.setDownloadBehavior", {
            "behavior": "allow",
            "downloadPath": download_dir
        })
    except:
        pass
    
    # Disable Safe Browsing via CDP
    try:
        driver.execute_cdp_cmd("Network.setBypassServiceWorker", {"bypass": True})
    except:
        pass
    
    login(driver)
    navigate_to_report(driver)
    isi_form(driver, tgl_box, jenis_aset)
    school_names, unit_input = get_school_names(driver, unit_name)
    print("Daftar sekolah yang ditemukan:", school_names)
    for school_name in school_names:
        try:
            download_excel_helper(driver, unit_input, school_name, download_dir)
        except Exception as e:
            print(f"Error saat download {school_name}: {e}")
    if not CLI_MODE:
        input("Tekan Enter untuk menutup browser...")
    driver.quit()
    if CLI_MODE and os.environ.get('KIB_JOB_DIR'):
        try:
            job_dir = os.environ.get('KIB_JOB_DIR')
            download_dir = os.environ.get('KIB_DOWNLOAD_DIR') or os.path.join(os.path.expanduser("~"), "Downloads")
            files = glob.glob(os.path.join(download_dir, "*.xls"))
            if files:
                open(os.path.join(job_dir, 'done.flag'), 'w').write('done')
            else:
                status_path = os.path.join(job_dir, 'status.json')
                job_id = os.path.basename(job_dir)
                status = {'job_id': job_id, 'status': 'error', 'meta': {'message': 'Tidak ada file terunduh'}, 'updated_at': int(time.time())}
                try:
                    open(status_path, 'w').write(json.dumps(status))
                except:
                    pass
        except:
            pass

# ===================== KIB B =====================
def run_kib_b():
    # KIB B
    def login(driver):
        driver.get("https://simbakda.sulselprov.go.id/index.php/welcome/login")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "username")))
        driver.find_element(By.NAME, "username").send_keys("DISDIK2023")
        driver.find_element(By.NAME, "password").send_keys("RAHMATGANTENG")
        Select(driver.find_element(By.NAME, "ta")).select_by_visible_text("2025")
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "button[type='submit']")))
        driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()

    def navigate_to_report(driver):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Laporan-Laporan")))
        laporan_menu = driver.find_element(By.LINK_TEXT, "Laporan-Laporan")
        ActionChains(driver).move_to_element(laporan_menu).click(laporan_menu).perform()
        WebDriverWait(driver, 5).until(EC.presence_of_element_located((By.LINK_TEXT, "Inventaris Barang")))
        driver.find_element(By.LINK_TEXT, "Inventaris Barang").click()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Aset Tetap")))
        aset_tetap_menu = driver.find_element(By.LINK_TEXT, "Aset Tetap")
        ActionChains(driver).move_to_element(aset_tetap_menu).click(aset_tetap_menu).perform()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Kib At")))
        kib_at_menu = driver.find_element(By.LINK_TEXT, "Kib At")
        ActionChains(driver).move_to_element(kib_at_menu).click(kib_at_menu).perform()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Peralatan & Mesin")))
        peralatan_mesin_menu = driver.find_element(By.LINK_TEXT, "Peralatan & Mesin")
        ActionChains(driver).move_to_element(peralatan_mesin_menu).click(peralatan_mesin_menu).perform()

    def isi_form(driver, tgl_value, jenis_aset):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text")))
        input_box = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text")
        input_box.clear()
        input_box.send_keys("1.01.01.00")
        time.sleep(1)
        input_box.send_keys("\ue007")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, "//input[@type='radio' and @name='format' and @value='fa']")))
        semua_jenis_radio = driver.find_element(By.XPATH, "//input[@type='radio' and @name='format' and @value='fa']")
        if not semua_jenis_radio.is_selected():
            semua_jenis_radio.click()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")))
        tgl_box_elem = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")
        tgl_box_elem.clear()
        tgl_box_elem.send_keys(tgl_value)
        aset_map = {
            "1": "jenis_aset_1",
            "2": "jenis_aset_2",
            "3": "jenis_aset_3"
        }
        aset_id = aset_map.get(jenis_aset, "jenis_aset_1")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.ID, aset_id)))
        jenis_aset_radio = driver.find_element(By.ID, aset_id)
        if not jenis_aset_radio.is_selected():
            jenis_aset_radio.click()

    def get_school_names(driver, unit_name):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, "//td[contains(.,'UNIT')]/following-sibling::td//input[contains(@class,'combo-text') and @autocomplete='off']")))
        unit_input = driver.find_element(By.XPATH, "//td[contains(.,'UNIT')]/following-sibling::td//input[contains(@class,'combo-text') and @autocomplete='off']")
        unit_input.clear()
        unit_input.send_keys(unit_name)
        time.sleep(2)
        school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row') and .//div[contains(@field,'nm_uskpd')]]")
        if not school_rows:
            school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row')]")
        school_names = []
        for row in school_rows:
            try:
                cells = row.find_elements(By.XPATH, ".//div[contains(@class,'datagrid-cell')]")
                if len(cells) >= 2:
                    name = cells[1].text
                    if name:
                        school_names.append(name)
            except:
                pass
        return school_names, unit_input

    def download_excel(driver, unit_input, school_name):
        unit_input.clear()
        unit_input.send_keys(school_name)
        time.sleep(2)
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")))
        school_row = driver.find_element(By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")
        school_row.click()
        time.sleep(2)
        download_dir = os.environ.get("KIB_DOWNLOAD_DIR") or os.path.join(os.path.expanduser("~"), "Downloads")
        files_before = set(glob.glob(os.path.join(download_dir, "*.xls")))
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "span.l-btn-text.icon-excel")))
        excel_btn = driver.find_element(By.CSS_SELECTOR, "span.l-btn-text.icon-excel")
        excel_btn.click()
        max_wait = 60
        waited = 0
        downloaded_file = None
        while waited < max_wait:
            files_after = set(glob.glob(os.path.join(download_dir, "*.xls")))
            new_files = files_after - files_before
            if new_files:
                downloaded_file = max(new_files, key=os.path.getctime)
                break
            time.sleep(1)
            waited += 1
        if downloaded_file:
            safe_school_name = "".join(c for c in school_name if c not in '\/:*?"<>|')
            new_name = f"{safe_school_name}.xls"
            new_path = os.path.join(download_dir, new_name)
            os.rename(downloaded_file, new_path)
            print(f"Renamed: {downloaded_file} -> {new_name}")

    def get_user_input():
        if CLI_MODE:
            return CLI_ARGS.get('tgl_box',''), CLI_ARGS.get('unit_name',''), CLI_ARGS.get('jenis_aset','1')
        root = tk.Tk()
        root.title("CETAK LAPORAN KIB B")
        root.geometry("300x200")
        tk.Label(root, text="Masukkan Nama Unit:").pack(pady=(10,0))
        unit_entry = tk.Entry(root)
        unit_entry.pack()
        tk.Label(root, text="KIB Per Tanggal:").pack(pady=(10,0))
        cal = DateEntry(root, date_pattern='yyyy-mm-dd')
        cal.pack()
        tk.Label(root, text="Jenis Aset:").pack(pady=(10,0))
        aset_options = ["Semua", "Intrakomptabel", "Ekstrakomptabel"]
        aset_values = {"Semua": "1", "Intrakomptabel": "2", "Ekstrakomptabel": "3"}
        aset_combo = ttk.Combobox(root, values=aset_options, state="readonly")
        aset_combo.current(0)
        aset_combo.pack()
        result = {}
        def validate(*args):
            if not unit_entry.get().strip():
                submit_btn.config(state="disabled")
            else:
                submit_btn.config(state="normal")
        def submit():
            if not unit_entry.get().strip():
                tk.messagebox.showerror("Error", "Nama Unit tidak boleh kosong!")
                return
            result['tgl_box'] = cal.get()
            result['unit_name'] = unit_entry.get()
            result['jenis_aset'] = aset_values.get(aset_combo.get(), "1")
            root.destroy()
        submit_btn = tk.Button(root, text="Download", command=submit)
        submit_btn.pack(pady=15)
        submit_btn.config(state="disabled")
        unit_entry.bind("<KeyRelease>", validate)
        root.mainloop()
        return result['tgl_box'], result['unit_name'], result['jenis_aset']

    tgl_box, unit_name, jenis_aset = get_user_input()
    chrome_options = Options()
    
    # Tentukan folder download
    download_dir = os.environ.get("KIB_DOWNLOAD_DIR") or os.path.join(os.path.expanduser("~"), "Downloads")
    
    # Konfigurasi preferences untuk download otomatis tanpa prompt
    prefs = {
        "download.default_directory": download_dir,
        "download.prompt_for_download": False,
        "download.directory_upgrade": True,
        "safebrowsing.enabled": False,
        "safebrowsing.disable_download_protection": True,
        "profile.default_content_setting_values.automatic_downloads": 1,
        "profile.content_settings.exceptions.automatic_downloads.*.setting": 1,
    }
    chrome_options.add_experimental_option("prefs", prefs)
    chrome_options.add_experimental_option("excludeSwitches", ["enable-logging"])
    
    # Opsi tambahan untuk mengizinkan download
    chrome_options.add_argument('--no-sandbox')
    chrome_options.add_argument('--disable-dev-shm-usage')
    chrome_options.add_argument('--disable-gpu')
    chrome_options.add_argument('--disable-web-security')
    chrome_options.add_argument('--allow-running-insecure-content')
    chrome_options.add_argument('--disable-features=IsolateOrigins,site-per-process,DownloadBubble,DownloadBubbleV2')
    chrome_options.add_argument('--disable-site-isolation-trials')
    # chrome_options.add_argument('--headless')  # Dinonaktifkan sementara untuk debugging
    chrome_options.add_argument('--window-size=1920,1080')
    
    driver = webdriver.Chrome(options=chrome_options)
    
    # Gunakan CDP untuk mengizinkan download
    driver.execute_cdp_cmd("Page.setDownloadBehavior", {
        "behavior": "allow",
        "downloadPath": download_dir
    })
    
    login(driver)
    navigate_to_report(driver)
    isi_form(driver, tgl_box, jenis_aset)
    school_names, unit_input = get_school_names(driver, unit_name)
    print("Daftar sekolah yang ditemukan:", school_names)
    for school_name in school_names:
        try:
            download_excel_helper(driver, unit_input, school_name, download_dir)
        except Exception as e:
            print(f"Error saat download {school_name}: {e}")
    if not CLI_MODE:
        input("Tekan Enter untuk menutup browser...")
    driver.quit()
    if CLI_MODE and os.environ.get('KIB_JOB_DIR'):
        try:
            job_dir = os.environ.get('KIB_JOB_DIR')
            download_dir = os.environ.get('KIB_DOWNLOAD_DIR') or os.path.join(os.path.expanduser("~"), "Downloads")
            files = glob.glob(os.path.join(download_dir, "*.xls"))
            if files:
                open(os.path.join(job_dir, 'done.flag'), 'w').write('done')
            else:
                status_path = os.path.join(job_dir, 'status.json')
                job_id = os.path.basename(job_dir)
                status = {'job_id': job_id, 'status': 'error', 'meta': {'message': 'Tidak ada file terunduh'}, 'updated_at': int(time.time())}
                try:
                    open(status_path, 'w').write(json.dumps(status))
                except:
                    pass
        except:
            pass
# ===================== KIB C =====================
def run_kib_c():
    # KIB C
    def login(driver):
        driver.get("https://simbakda.sulselprov.go.id/index.php/welcome/login")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "username")))
        driver.find_element(By.NAME, "username").send_keys("DISDIK2023")
        driver.find_element(By.NAME, "password").send_keys("RAHMATGANTENG")
        Select(driver.find_element(By.NAME, "ta")).select_by_visible_text("2025")
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "button[type='submit']")))
        driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()

    def navigate_to_report(driver):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Laporan-Laporan")))
        laporan_menu = driver.find_element(By.LINK_TEXT, "Laporan-Laporan")
        ActionChains(driver).move_to_element(laporan_menu).click(laporan_menu).perform()
        WebDriverWait(driver, 5).until(EC.presence_of_element_located((By.LINK_TEXT, "Inventaris Barang")))
        driver.find_element(By.LINK_TEXT, "Inventaris Barang").click()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Aset Tetap")))
        aset_tetap_menu = driver.find_element(By.LINK_TEXT, "Aset Tetap")
        ActionChains(driver).move_to_element(aset_tetap_menu).click(aset_tetap_menu).perform()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Kib At")))
        kib_at_menu = driver.find_element(By.LINK_TEXT, "Kib At")
        ActionChains(driver).move_to_element(kib_at_menu).click(kib_at_menu).perform()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Gedung & Bangunan")))
        gedung_bangunan_menu = driver.find_element(By.LINK_TEXT, "Gedung & Bangunan")
        ActionChains(driver).move_to_element(gedung_bangunan_menu).click(gedung_bangunan_menu).perform()

    def isi_form(driver, tgl_value, jenis_aset, jenis_kode):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text")))
        input_box = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text")
        input_box.clear()
        input_box.send_keys("1.01.01.00")
        time.sleep(1)
        input_box.send_keys("\ue007")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.ID, "jns_kode")))
        jenis_kode_select = Select(driver.find_element(By.ID, "jns_kode"))
        jenis_kode_select.select_by_value(jenis_kode)
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, "//input[@type='radio' and @name='format' and @value='fa']")))
        semua_jenis_radio = driver.find_element(By.XPATH, "//input[@type='radio' and @name='format' and @value='fa']")
        if not semua_jenis_radio.is_selected():
            semua_jenis_radio.click()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")))
        tgl_box_elem = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")
        tgl_box_elem.clear()
        tgl_box_elem.send_keys(tgl_value)
        aset_map = {
            "1": "jenis_aset_1",
            "2": "jenis_aset_2",
            "3": "jenis_aset_3"
        }
        aset_id = aset_map.get(jenis_aset, "jenis_aset_1")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.ID, aset_id)))
        jenis_aset_radio = driver.find_element(By.ID, aset_id)
        if not jenis_aset_radio.is_selected():
            jenis_aset_radio.click()

    def get_school_names(driver, unit_name):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, "//td[contains(.,'UNIT')]/following-sibling::td//input[contains(@class,'combo-text') and @autocomplete='off']")))
        unit_input = driver.find_element(By.XPATH, "//td[contains(.,'UNIT')]/following-sibling::td//input[contains(@class,'combo-text') and @autocomplete='off']")
        unit_input.clear()
        unit_input.send_keys(unit_name)
        time.sleep(2)
        school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row') and .//div[contains(@field,'nm_uskpd')]]")
        if not school_rows:
            school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row')]")
        school_names = []
        for row in school_rows:
            try:
                cells = row.find_elements(By.XPATH, ".//div[contains(@class,'datagrid-cell')]")
                if len(cells) >= 2:
                    name = cells[1].text
                    if name:
                        school_names.append(name)
            except:
                pass
        return school_names, unit_input

    def download_excel(driver, unit_input, school_name):
        unit_input.clear()
        unit_input.send_keys(school_name)
        time.sleep(2)
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")))
        school_row = driver.find_element(By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")
        school_row.click()
        time.sleep(2)
        download_dir = os.environ.get("KIB_DOWNLOAD_DIR") or os.path.join(os.path.expanduser("~"), "Downloads")
        files_before = set(glob.glob(os.path.join(download_dir, "*.xls")))
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "span.l-btn-text.icon-excel")))
        excel_btn = driver.find_element(By.CSS_SELECTOR, "span.l-btn-text.icon-excel")
        excel_btn.click()
        max_wait = 60
        waited = 0
        downloaded_file = None
        while waited < max_wait:
            files_after = set(glob.glob(os.path.join(download_dir, "*.xls")))
            new_files = files_after - files_before
            if new_files:
                downloaded_file = max(new_files, key=os.path.getctime)
                break
            time.sleep(1)
            waited += 1
        if downloaded_file:
            safe_school_name = "".join(c for c in school_name if c not in '\/:*?"<>|')
            new_name = f"{safe_school_name}.xls"
            new_path = os.path.join(download_dir, new_name)
            os.rename(downloaded_file, new_path)
            print(f"Renamed: {downloaded_file} -> {new_name}")

    def get_user_input():
        if CLI_MODE:
            return CLI_ARGS.get('tgl_box',''), CLI_ARGS.get('unit_name',''), CLI_ARGS.get('jenis_aset','1'), CLI_ARGS.get('jenis_kode','1')
        root = tk.Tk()
        root.title("CETAK LAPORAN KIB C")
        root.geometry("300x250")  # Diperbesar untuk menampung field baru
        
        tk.Label(root, text="Masukkan Nama Unit:").pack(pady=(10,0))
        unit_entry = tk.Entry(root)
        unit_entry.pack()
        
        tk.Label(root, text="KIB Per Tanggal:").pack(pady=(10,0))
        cal = DateEntry(root, date_pattern='yyyy-mm-dd')
        cal.pack()
        
        tk.Label(root, text="Jenis Aset:").pack(pady=(10,0))
        aset_options = ["Semua", "Intrakomptabel", "Ekstrakomptabel"]
        aset_values = {"Semua": "1", "Intrakomptabel": "2", "Ekstrakomptabel": "3"}
        aset_combo = ttk.Combobox(root, values=aset_options, state="readonly")
        aset_combo.current(0)
        aset_combo.pack()
        
        # TAMBAHAN: Dropdown untuk jenis kode barang
        tk.Label(root, text="Jenis Kode Barang:").pack(pady=(10,0))
        kode_options = ["Kode Barang Lama", "Kode Barang Baru"]
        kode_values = {"Kode Barang Lama": "1", "Kode Barang Baru": "2"}
        kode_combo = ttk.Combobox(root, values=kode_options, state="readonly")
        kode_combo.current(0)
        kode_combo.pack()
        
        result = {}
        submit_btn = tk.Button(root, text="Download")
        
        def validate(*args):
            if not unit_entry.get().strip():
                submit_btn.config(state="disabled")
            else:
                submit_btn.config(state="normal")
        
        def submit():
            if not unit_entry.get().strip():
                tk.messagebox.showerror("Error", "Nama Unit tidak boleh kosong!")
                return
            result['tgl_box'] = cal.get()
            result['unit_name'] = unit_entry.get()
            result['jenis_aset'] = aset_values.get(aset_combo.get(), "1")
            result['jenis_kode'] = kode_values.get(kode_combo.get(), "1")  # TAMBAHAN
            root.destroy()
        
        submit_btn.config(command=submit)
        submit_btn.pack(pady=15)
        submit_btn.config(state="disabled")
        unit_entry.bind("<KeyRelease>", validate)
        
        root.mainloop()
        return result['tgl_box'], result['unit_name'], result['jenis_aset'], result['jenis_kode']  # TAMBAHAN

    tgl_box, unit_name, jenis_aset, jenis_kode = get_user_input()  # TAMBAH PARAMETER
    chrome_options = Options()
    
    # Tentukan folder download
    download_dir = os.environ.get("KIB_DOWNLOAD_DIR") or os.path.join(os.path.expanduser("~"), "Downloads")
    
    # Konfigurasi preferences untuk download otomatis tanpa prompt
    prefs = {
        "download.default_directory": download_dir,
        "download.prompt_for_download": False,
        "download.directory_upgrade": True,
        "safebrowsing.enabled": False,
        "safebrowsing.disable_download_protection": True,
        "profile.default_content_setting_values.automatic_downloads": 1,
        "profile.content_settings.exceptions.automatic_downloads.*.setting": 1,
    }
    chrome_options.add_experimental_option("prefs", prefs)
    chrome_options.add_experimental_option("excludeSwitches", ["enable-logging"])
    
    # Opsi tambahan untuk mengizinkan download
    chrome_options.add_argument('--no-sandbox')
    chrome_options.add_argument('--disable-dev-shm-usage')
    chrome_options.add_argument('--disable-gpu')
    chrome_options.add_argument('--disable-web-security')
    chrome_options.add_argument('--allow-running-insecure-content')
    chrome_options.add_argument('--disable-features=IsolateOrigins,site-per-process,DownloadBubble,DownloadBubbleV2')
    chrome_options.add_argument('--disable-site-isolation-trials')
    # chrome_options.add_argument('--headless')  # Dinonaktifkan sementara untuk debugging
    chrome_options.add_argument('--window-size=1920,1080')
    
    driver = webdriver.Chrome(options=chrome_options)
    
    # Gunakan CDP untuk mengizinkan download
    driver.execute_cdp_cmd("Page.setDownloadBehavior", {
        "behavior": "allow",
        "downloadPath": download_dir
    })
    
    login(driver)
    navigate_to_report(driver)
    isi_form(driver, tgl_box, jenis_aset, jenis_kode)  # TAMBAH PARAMETER
    school_names, unit_input = get_school_names(driver, unit_name)
    print("Daftar sekolah yang ditemukan:", school_names)
    for school_name in school_names:
        try:
            download_excel_helper(driver, unit_input, school_name, download_dir)
        except Exception as e:
            print(f"Error saat download {school_name}: {e}")
    if not CLI_MODE:
        input("Tekan Enter untuk menutup browser...")
    driver.quit()
    if CLI_MODE and os.environ.get('KIB_JOB_DIR'):
        try:
            job_dir = os.environ.get('KIB_JOB_DIR')
            download_dir = os.environ.get('KIB_DOWNLOAD_DIR') or os.path.join(os.path.expanduser("~"), "Downloads")
            files = glob.glob(os.path.join(download_dir, "*.xls"))
            if files:
                open(os.path.join(job_dir, 'done.flag'), 'w').write('done')
            else:
                status_path = os.path.join(job_dir, 'status.json')
                job_id = os.path.basename(job_dir)
                status = {'job_id': job_id, 'status': 'error', 'meta': {'message': 'Tidak ada file terunduh'}, 'updated_at': int(time.time())}
                try:
                    open(status_path, 'w').write(json.dumps(status))
                except:
                    pass
        except:
            pass
# ===================== KIB D =====================
def run_kib_d():
    # KIB D
    def login(driver):
        driver.get("https://simbakda.sulselprov.go.id/index.php/welcome/login")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "username")))
        driver.find_element(By.NAME, "username").send_keys("DISDIK2023")
        driver.find_element(By.NAME, "password").send_keys("RAHMATGANTENG")
        Select(driver.find_element(By.NAME, "ta")).select_by_visible_text("2025")
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "button[type='submit']")))
        driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()

    def navigate_to_report(driver):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Laporan-Laporan")))
        laporan_menu = driver.find_element(By.LINK_TEXT, "Laporan-Laporan")
        ActionChains(driver).move_to_element(laporan_menu).click(laporan_menu).perform()
        WebDriverWait(driver, 5).until(EC.presence_of_element_located((By.LINK_TEXT, "Inventaris Barang")))
        driver.find_element(By.LINK_TEXT, "Inventaris Barang").click()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Aset Tetap")))
        aset_tetap_menu = driver.find_element(By.LINK_TEXT, "Aset Tetap")
        ActionChains(driver).move_to_element(aset_tetap_menu).click(aset_tetap_menu).perform()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Kib At")))
        kib_at_menu = driver.find_element(By.LINK_TEXT, "Kib At")
        ActionChains(driver).move_to_element(kib_at_menu).click(kib_at_menu).perform()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Jalan Irigasi & Jaringan")))
        jalanirigasi_jaringan_menu = driver.find_element(By.LINK_TEXT, "Jalan Irigasi & Jaringan")
        ActionChains(driver).move_to_element(jalanirigasi_jaringan_menu).click(jalanirigasi_jaringan_menu).perform()

    def isi_form(driver, tgl_value, jenis_aset, jenis_kode):  # TAMBAH PARAMETER jenis_kode
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text")))
        input_box = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text")
        input_box.clear()
        input_box.send_keys("1.01.01.00")
        time.sleep(1)
        input_box.send_keys("\ue007")
        
        # TAMBAHAN: Pilih jenis kode barang
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.ID, "jns_kode")))
        jenis_kode_select = Select(driver.find_element(By.ID, "jns_kode"))
        jenis_kode_select.select_by_value(jenis_kode)
        
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, "//input[@type='radio' and @name='format' and @value='fa']")))
        semua_jenis_radio = driver.find_element(By.XPATH, "//input[@type='radio' and @name='format' and @value='fa']")
        if not semua_jenis_radio.is_selected():
            semua_jenis_radio.click()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")))
        tgl_box_elem = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")
        tgl_box_elem.clear()
        tgl_box_elem.send_keys(tgl_value)
        aset_map = {
            "1": "jenis_aset_1",
            "2": "jenis_aset_2",
            "3": "jenis_aset_3"
        }
        aset_id = aset_map.get(jenis_aset, "jenis_aset_1")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.ID, aset_id)))
        jenis_aset_radio = driver.find_element(By.ID, aset_id)
        if not jenis_aset_radio.is_selected():
            jenis_aset_radio.click()

    def get_school_names(driver, unit_name):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, "//td[contains(.,'UNIT')]/following-sibling::td//input[contains(@class,'combo-text') and @autocomplete='off']")))
        unit_input = driver.find_element(By.XPATH, "//td[contains(.,'UNIT')]/following-sibling::td//input[contains(@class,'combo-text') and @autocomplete='off']")
        unit_input.clear()
        unit_input.send_keys(unit_name)
        time.sleep(2)
        school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row') and .//div[contains(@field,'nm_uskpd')]]")
        if not school_rows:
            school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row')]")
        school_names = []
        for row in school_rows:
            try:
                cells = row.find_elements(By.XPATH, ".//div[contains(@class,'datagrid-cell')]")
                if len(cells) >= 2:
                    name = cells[1].text
                    if name:
                        school_names.append(name)
            except:
                pass
        return school_names, unit_input

    def download_excel(driver, unit_input, school_name):
        unit_input.clear()
        unit_input.send_keys(school_name)
        time.sleep(2)
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")))
        school_row = driver.find_element(By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")
        school_row.click()
        time.sleep(2)
        download_dir = os.environ.get("KIB_DOWNLOAD_DIR") or os.path.join(os.path.expanduser("~"), "Downloads")
        files_before = set(glob.glob(os.path.join(download_dir, "*.xls")))
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "span.l-btn-text.icon-excel")))
        excel_btn = driver.find_element(By.CSS_SELECTOR, "span.l-btn-text.icon-excel")
        excel_btn.click()
        max_wait = 60
        waited = 0
        downloaded_file = None
        while waited < max_wait:
            files_after = set(glob.glob(os.path.join(download_dir, "*.xls")))
            new_files = files_after - files_before
            if new_files:
                downloaded_file = max(new_files, key=os.path.getctime)
                break
            time.sleep(1)
            waited += 1
        if downloaded_file:
            safe_school_name = "".join(c for c in school_name if c not in '\/:*?"<>|')
            new_name = f"{safe_school_name}.xls"
            new_path = os.path.join(download_dir, new_name)
            os.rename(downloaded_file, new_path)
            print(f"Renamed: {downloaded_file} -> {new_name}")

    def get_user_input():
        if CLI_MODE:
            return CLI_ARGS.get('tgl_box',''), CLI_ARGS.get('unit_name',''), CLI_ARGS.get('jenis_aset','1'), CLI_ARGS.get('jenis_kode','1')
        root = tk.Tk()
        root.title("CETAK LAPORAN KIB D")
        root.geometry("300x250")  # Diperbesar untuk menampung field baru
        
        tk.Label(root, text="Masukkan Nama Unit:").pack(pady=(10,0))
        unit_entry = tk.Entry(root)
        unit_entry.pack()
        
        tk.Label(root, text="KIB Per Tanggal:").pack(pady=(10,0))
        cal = DateEntry(root, date_pattern='yyyy-mm-dd')
        cal.pack()
        
        tk.Label(root, text="Jenis Aset:").pack(pady=(10,0))
        aset_options = ["Semua", "Intrakomptabel", "Ekstrakomptabel"]
        aset_values = {"Semua": "1", "Intrakomptabel": "2", "Ekstrakomptabel": "3"}
        aset_combo = ttk.Combobox(root, values=aset_options, state="readonly")
        aset_combo.current(0)
        aset_combo.pack()
        
        # TAMBAHAN: Dropdown untuk jenis kode barang
        tk.Label(root, text="Jenis Kode Barang:").pack(pady=(10,0))
        kode_options = ["Kode Barang Lama", "Kode Barang Baru"]
        kode_values = {"Kode Barang Lama": "1", "Kode Barang Baru": "2"}
        kode_combo = ttk.Combobox(root, values=kode_options, state="readonly")
        kode_combo.current(0)
        kode_combo.pack()
        
        result = {}
        submit_btn = tk.Button(root, text="Download")
        
        def validate(*args):
            if not unit_entry.get().strip():
                submit_btn.config(state="disabled")
            else:
                submit_btn.config(state="normal")
        
        def submit():
            if not unit_entry.get().strip():
                tk.messagebox.showerror("Error", "Nama Unit tidak boleh kosong!")
                return
            result['tgl_box'] = cal.get()
            result['unit_name'] = unit_entry.get()
            result['jenis_aset'] = aset_values.get(aset_combo.get(), "1")
            result['jenis_kode'] = kode_values.get(kode_combo.get(), "1")  # TAMBAHAN
            root.destroy()
        
        submit_btn.config(command=submit)
        submit_btn.pack(pady=15)
        submit_btn.config(state="disabled")
        unit_entry.bind("<KeyRelease>", validate)
        
        root.mainloop()
        return result['tgl_box'], result['unit_name'], result['jenis_aset'], result['jenis_kode']  # TAMBAHAN

    tgl_box, unit_name, jenis_aset, jenis_kode = get_user_input()  # TAMBAH PARAMETER
    chrome_options = Options()
    
    # Tentukan folder download
    download_dir = os.environ.get("KIB_DOWNLOAD_DIR") or os.path.join(os.path.expanduser("~"), "Downloads")
    
    # Konfigurasi preferences untuk download otomatis tanpa prompt
    prefs = {
        "download.default_directory": download_dir,
        "download.prompt_for_download": False,
        "download.directory_upgrade": True,
        "safebrowsing.enabled": False,
        "safebrowsing.disable_download_protection": True,
        "profile.default_content_setting_values.automatic_downloads": 1,
        "profile.content_settings.exceptions.automatic_downloads.*.setting": 1,
    }
    chrome_options.add_experimental_option("prefs", prefs)
    chrome_options.add_experimental_option("excludeSwitches", ["enable-logging"])
    
    # Opsi tambahan untuk mengizinkan download
    chrome_options.add_argument('--no-sandbox')
    chrome_options.add_argument('--disable-dev-shm-usage')
    chrome_options.add_argument('--disable-gpu')
    chrome_options.add_argument('--disable-web-security')
    chrome_options.add_argument('--allow-running-insecure-content')
    chrome_options.add_argument('--disable-features=IsolateOrigins,site-per-process,DownloadBubble,DownloadBubbleV2')
    chrome_options.add_argument('--disable-site-isolation-trials')
    # chrome_options.add_argument('--headless')  # Dinonaktifkan sementara untuk debugging
    chrome_options.add_argument('--window-size=1920,1080')
    
    driver = webdriver.Chrome(options=chrome_options)
    
    # Gunakan CDP untuk mengizinkan download
    driver.execute_cdp_cmd("Page.setDownloadBehavior", {
        "behavior": "allow",
        "downloadPath": download_dir
    })
    
    login(driver)
    navigate_to_report(driver)
    isi_form(driver, tgl_box, jenis_aset, jenis_kode)  # TAMBAH PARAMETER
    school_names, unit_input = get_school_names(driver, unit_name)
    print("Daftar sekolah yang ditemukan:", school_names)
    for school_name in school_names:
        try:
            download_excel_helper(driver, unit_input, school_name, download_dir)
        except Exception as e:
            print(f"Error saat download {school_name}: {e}")
    if not CLI_MODE:
        input("Tekan Enter untuk menutup browser...")
    driver.quit()
    if CLI_MODE and os.environ.get('KIB_JOB_DIR'):
        try:
            job_dir = os.environ.get('KIB_JOB_DIR')
            download_dir = os.environ.get('KIB_DOWNLOAD_DIR') or os.path.join(os.path.expanduser("~"), "Downloads")
            files = glob.glob(os.path.join(download_dir, "*.xls"))
            if files:
                open(os.path.join(job_dir, 'done.flag'), 'w').write('done')
            else:
                status_path = os.path.join(job_dir, 'status.json')
                job_id = os.path.basename(job_dir)
                status = {'job_id': job_id, 'status': 'error', 'meta': {'message': 'Tidak ada file terunduh'}, 'updated_at': int(time.time())}
                try:
                    open(status_path, 'w').write(json.dumps(status))
                except:
                    pass
        except:
            pass
# ===================== KIB E =====================
def run_kib_e():
    # KIB E
    def login(driver):
        driver.get("https://simbakda.sulselprov.go.id/index.php/welcome/login")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "username")))
        driver.find_element(By.NAME, "username").send_keys("DISDIK2023")
        driver.find_element(By.NAME, "password").send_keys("RAHMATGANTENG")
        Select(driver.find_element(By.NAME, "ta")).select_by_visible_text("2025")
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "button[type='submit']")))
        driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()

    def navigate_to_report(driver):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Laporan-Laporan")))
        laporan_menu = driver.find_element(By.LINK_TEXT, "Laporan-Laporan")
        ActionChains(driver).move_to_element(laporan_menu).click(laporan_menu).perform()
        WebDriverWait(driver, 5).until(EC.presence_of_element_located((By.LINK_TEXT, "Inventaris Barang")))
        driver.find_element(By.LINK_TEXT, "Inventaris Barang").click()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Aset Tetap")))
        aset_tetap_menu = driver.find_element(By.LINK_TEXT, "Aset Tetap")
        ActionChains(driver).move_to_element(aset_tetap_menu).click(aset_tetap_menu).perform()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Kib At")))
        kib_at_menu = driver.find_element(By.LINK_TEXT, "Kib At")
        ActionChains(driver).move_to_element(kib_at_menu).click(kib_at_menu).perform()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "At Lainnya")))
        at_lainnya_menu = driver.find_element(By.LINK_TEXT, "At Lainnya")
        ActionChains(driver).move_to_element(at_lainnya_menu).click(at_lainnya_menu).perform()

    def isi_form(driver, tgl_value, jenis_aset):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text")))
        input_box = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text")
        input_box.clear()
        input_box.send_keys("1.01.01.00")
        time.sleep(1)
        input_box.send_keys("\ue007")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, "//input[@type='radio' and @name='format' and @value='fa']")))
        semua_jenis_radio = driver.find_element(By.XPATH, "//input[@type='radio' and @name='format' and @value='fa']")
        if not semua_jenis_radio.is_selected():
            semua_jenis_radio.click()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")))
        tgl_box_elem = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")
        tgl_box_elem.clear()
        tgl_box_elem.send_keys(tgl_value)
        aset_map = {
            "1": "jenis_aset_1",
            "2": "jenis_aset_2",
            "3": "jenis_aset_3"
        }
        aset_id = aset_map.get(jenis_aset, "jenis_aset_1")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.ID, aset_id)))
        jenis_aset_radio = driver.find_element(By.ID, aset_id)
        if not jenis_aset_radio.is_selected():
            jenis_aset_radio.click()

    def get_school_names(driver, unit_name):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, "//td[contains(.,'UNIT')]/following-sibling::td//input[contains(@class,'combo-text') and @autocomplete='off']")))
        unit_input = driver.find_element(By.XPATH, "//td[contains(.,'UNIT')]/following-sibling::td//input[contains(@class,'combo-text') and @autocomplete='off']")
        unit_input.clear()
        unit_input.send_keys(unit_name)
        time.sleep(2)
        school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row') and .//div[contains(@field,'nm_uskpd')]]")
        if not school_rows:
            school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row')]")
        school_names = []
        for row in school_rows:
            try:
                cells = row.find_elements(By.XPATH, ".//div[contains(@class,'datagrid-cell')]")
                if len(cells) >= 2:
                    name = cells[1].text
                    if name:
                        school_names.append(name)
            except:
                pass
        return school_names, unit_input

    def download_excel(driver, unit_input, school_name):
        unit_input.clear()
        unit_input.send_keys(school_name)
        time.sleep(2)
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")))
        school_row = driver.find_element(By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")
        school_row.click()
        time.sleep(2)
        download_dir = os.environ.get("KIB_DOWNLOAD_DIR") or os.path.join(os.path.expanduser("~"), "Downloads")
        files_before = set(glob.glob(os.path.join(download_dir, "*.xls")))
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "span.l-btn-text.icon-excel")))
        excel_btn = driver.find_element(By.CSS_SELECTOR, "span.l-btn-text.icon-excel")
        excel_btn.click()
        max_wait = 60
        waited = 0
        downloaded_file = None
        while waited < max_wait:
            files_after = set(glob.glob(os.path.join(download_dir, "*.xls")))
            new_files = files_after - files_before
            if new_files:
                downloaded_file = max(new_files, key=os.path.getctime)
                break
            time.sleep(1)
            waited += 1
        if downloaded_file:
            safe_school_name = "".join(c for c in school_name if c not in '\/:*?"<>|')
            new_name = f"{safe_school_name}.xls"
            new_path = os.path.join(download_dir, new_name)
            os.rename(downloaded_file, new_path)
            print(f"Renamed: {downloaded_file} -> {new_name}")

    def get_user_input():
        if CLI_MODE:
            return CLI_ARGS.get('tgl_box',''), CLI_ARGS.get('unit_name',''), CLI_ARGS.get('jenis_aset','1')
        root = tk.Tk()
        root.title("CETAK LAPORAN KIB E")
        root.geometry("300x200")
        tk.Label(root, text="Masukkan Nama Unit:").pack(pady=(10,0))
        unit_entry = tk.Entry(root)
        unit_entry.pack()
        tk.Label(root, text="KIB Per Tanggal:").pack(pady=(10,0))
        cal = DateEntry(root, date_pattern='yyyy-mm-dd')
        cal.pack()
        tk.Label(root, text="Jenis Aset:").pack(pady=(10,0))
        aset_options = ["Semua", "Intrakomptabel", "Ekstrakomptabel"]
        aset_values = {"Semua": "1", "Intrakomptabel": "2", "Ekstrakomptabel": "3"}
        aset_combo = ttk.Combobox(root, values=aset_options, state="readonly")
        aset_combo.current(0)
        aset_combo.pack()
        result = {}
        submit_btn = tk.Button(root, text="Download")
        def validate(*args):
            if not unit_entry.get().strip():
                submit_btn.config(state="disabled")
            else:
                submit_btn.config(state="normal")
        def submit():
            if not unit_entry.get().strip():
                tk.messagebox.showerror("Error", "Nama Unit tidak boleh kosong!")
                return
            result['tgl_box'] = cal.get()
            result['unit_name'] = unit_entry.get()
            result['jenis_aset'] = aset_values.get(aset_combo.get(), "1")
            root.destroy()
        submit_btn.config(command=submit)
        submit_btn.pack(pady=15)
        submit_btn.config(state="disabled")
        unit_entry.bind("<KeyRelease>", validate)
        root.mainloop()
        return result['tgl_box'], result['unit_name'], result['jenis_aset']

    tgl_box, unit_name, jenis_aset = get_user_input()
    chrome_options = Options()
    
    # Tentukan folder download
    download_dir = os.environ.get("KIB_DOWNLOAD_DIR") or os.path.join(os.path.expanduser("~"), "Downloads")
    
    # Konfigurasi preferences untuk download otomatis tanpa prompt
    prefs = {
        "download.default_directory": download_dir,
        "download.prompt_for_download": False,
        "download.directory_upgrade": True,
        "safebrowsing.enabled": False,
        "safebrowsing.disable_download_protection": True,
        "profile.default_content_setting_values.automatic_downloads": 1,
        "profile.content_settings.exceptions.automatic_downloads.*.setting": 1,
    }
    chrome_options.add_experimental_option("prefs", prefs)
    chrome_options.add_experimental_option("excludeSwitches", ["enable-logging"])
    
    # Opsi tambahan untuk mengizinkan download
    chrome_options.add_argument('--no-sandbox')
    chrome_options.add_argument('--disable-dev-shm-usage')
    chrome_options.add_argument('--disable-gpu')
    chrome_options.add_argument('--disable-web-security')
    chrome_options.add_argument('--allow-running-insecure-content')
    chrome_options.add_argument('--disable-features=IsolateOrigins,site-per-process,DownloadBubble,DownloadBubbleV2')
    chrome_options.add_argument('--disable-site-isolation-trials')
    # chrome_options.add_argument('--headless')  # Dinonaktifkan sementara untuk debugging
    chrome_options.add_argument('--window-size=1920,1080')
    
    driver = webdriver.Chrome(options=chrome_options)
    
    # Gunakan CDP untuk mengizinkan download
    driver.execute_cdp_cmd("Page.setDownloadBehavior", {
        "behavior": "allow",
        "downloadPath": download_dir
    })
    
    login(driver)
    navigate_to_report(driver)
    isi_form(driver, tgl_box, jenis_aset)
    school_names, unit_input = get_school_names(driver, unit_name)
    print("Daftar sekolah yang ditemukan:", school_names)
    for school_name in school_names:
        try:
            download_excel_helper(driver, unit_input, school_name, download_dir)
        except Exception as e:
            print(f"Error saat download {school_name}: {e}")
    if not CLI_MODE:
        input("Tekan Enter untuk menutup browser...")
    driver.quit()
    if CLI_MODE and os.environ.get('KIB_JOB_DIR'):
        try:
            job_dir = os.environ.get('KIB_JOB_DIR')
            download_dir = os.environ.get('KIB_DOWNLOAD_DIR') or os.path.join(os.path.expanduser("~"), "Downloads")
            files = glob.glob(os.path.join(download_dir, "*.xls"))
            if files:
                open(os.path.join(job_dir, 'done.flag'), 'w').write('done')
            else:
                status_path = os.path.join(job_dir, 'status.json')
                job_id = os.path.basename(job_dir)
                status = {'job_id': job_id, 'status': 'error', 'meta': {'message': 'Tidak ada file terunduh'}, 'updated_at': int(time.time())}
                try:
                    open(status_path, 'w').write(json.dumps(status))
                except:
                    pass
        except:
            pass
# ===================== KIB F =====================
def run_kib_f():
    # KIB F
    def login(driver):
        driver.get("https://simbakda.sulselprov.go.id/index.php/welcome/login")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "username")))
        driver.find_element(By.NAME, "username").send_keys("DISDIK2023")
        driver.find_element(By.NAME, "password").send_keys("RAHMATGANTENG")
        Select(driver.find_element(By.NAME, "ta")).select_by_visible_text("2025")
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "button[type='submit']")))
        driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()

    def navigate_to_report(driver):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Laporan-Laporan")))
        laporan_menu = driver.find_element(By.LINK_TEXT, "Laporan-Laporan")
        ActionChains(driver).move_to_element(laporan_menu).click(laporan_menu).perform()
        WebDriverWait(driver, 5).until(EC.presence_of_element_located((By.LINK_TEXT, "Inventaris Barang")))
        driver.find_element(By.LINK_TEXT, "Inventaris Barang").click()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Aset Tetap")))
        aset_tetap_menu = driver.find_element(By.LINK_TEXT, "Aset Tetap")
        ActionChains(driver).move_to_element(aset_tetap_menu).click(aset_tetap_menu).perform()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Kib At")))
        kib_at_menu = driver.find_element(By.LINK_TEXT, "Kib At")
        ActionChains(driver).move_to_element(kib_at_menu).click(kib_at_menu).perform()
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Kdp")))
        kdp_menu = driver.find_element(By.LINK_TEXT, "Kdp")
        ActionChains(driver).move_to_element(kdp_menu).click(kdp_menu).perform()

    def isi_form(driver, tgl_value):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text")))
        input_box = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text")
        input_box.clear()
        input_box.send_keys("1.01.01.00")
        time.sleep(1)
        input_box.send_keys("\ue007")
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")))
        tgl_box_elem = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")
        tgl_box_elem.clear()
        tgl_box_elem.send_keys(tgl_value)

    def get_school_names(driver, unit_name):
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, "//*[@id='div_skpd']/table/tbody/tr/td[3]/span/input[1]")))
        unit_input = driver.find_element(By.XPATH, "//*[@id='div_skpd']/table/tbody/tr/td[3]/span/input[1]")
        unit_input.clear()
        unit_input.send_keys(unit_name)
        time.sleep(2)
        school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row') and .//div[contains(@field,'nm_uskpd')]]")
        if not school_rows:
            school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row')]")
        school_names = []
        for row in school_rows:
            try:
                cells = row.find_elements(By.XPATH, ".//div[contains(@class,'datagrid-cell')]")
                if len(cells) >= 2:
                    name = cells[1].text
                    if name:
                        school_names.append(name)
            except:
                pass
        return school_names, unit_input

    def download_excel(driver, unit_input, school_name):
        unit_input.clear()
        unit_input.send_keys(school_name)
        time.sleep(2)
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")))
        school_row = driver.find_element(By.XPATH, f"//tr[contains(@class,'datagrid-row') and .//div[text()='{school_name}']]")
        school_row.click()
        time.sleep(2)
        download_dir = os.environ.get("KIB_DOWNLOAD_DIR") or os.path.join(os.path.expanduser("~"), "Downloads")
        files_before = set(glob.glob(os.path.join(download_dir, "*.xls")))
        WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "span.l-btn-text.icon-excel")))
        excel_btn = driver.find_element(By.CSS_SELECTOR, "span.l-btn-text.icon-excel")
        excel_btn.click()
        max_wait = 60
        waited = 0
        downloaded_file = None
        while waited < max_wait:
            files_after = set(glob.glob(os.path.join(download_dir, "*.xls")))
            new_files = files_after - files_before
            if new_files:
                downloaded_file = max(new_files, key=os.path.getctime)
                break
            time.sleep(1)
            waited += 1
        if downloaded_file:
            safe_school_name = "".join(c for c in school_name if c not in '\/:*?"<>|')
            new_name = f"{safe_school_name}.xls"
            new_path = os.path.join(download_dir, new_name)
            os.rename(downloaded_file, new_path)
            print(f"Renamed: {downloaded_file} -> {new_name}")

    def get_user_input():
        if CLI_MODE:
            return CLI_ARGS.get('tgl_box',''), CLI_ARGS.get('unit_name','')
        root = tk.Tk()
        root.title("CETAK LAPORAN KIB F")
        root.geometry("300x200")
        tk.Label(root, text="Masukkan Nama Unit:").pack(pady=(10,0))
        unit_entry = tk.Entry(root)
        unit_entry.pack()
        tk.Label(root, text="KIB Per Tanggal:").pack(pady=(10,0))
        cal = DateEntry(root, date_pattern='yyyy-mm-dd')
        cal.pack()
        result = {}
        submit_btn = tk.Button(root, text="Download")
        def validate(*args):
            if not unit_entry.get().strip():
                submit_btn.config(state="disabled")
            else:
                submit_btn.config(state="normal")
        def submit():
            if not unit_entry.get().strip():
                tk.messagebox.showerror("Error", "Nama Unit tidak boleh kosong!")
                return
            result['tgl_box'] = cal.get()
            result['unit_name'] = unit_entry.get()
            root.destroy()
        submit_btn.config(command=submit)
        submit_btn.pack(pady=15)
        submit_btn.config(state="disabled")
        unit_entry.bind("<KeyRelease>", validate)
        root.mainloop()
        return result['tgl_box'], result['unit_name']

    tgl_box, unit_name = get_user_input()
    chrome_options = Options()
    
    # Tentukan folder download
    download_dir = os.environ.get("KIB_DOWNLOAD_DIR") or os.path.join(os.path.expanduser("~"), "Downloads")
    
    # Konfigurasi preferences untuk download otomatis tanpa prompt
    prefs = {
        "download.default_directory": download_dir,
        "download.prompt_for_download": False,
        "download.directory_upgrade": True,
        "safebrowsing.enabled": False,
        "safebrowsing.disable_download_protection": True,
        "profile.default_content_setting_values.automatic_downloads": 1,
        "profile.content_settings.exceptions.automatic_downloads.*.setting": 1,
    }
    chrome_options.add_experimental_option("prefs", prefs)
    chrome_options.add_experimental_option("excludeSwitches", ["enable-logging"])
    
    # Opsi tambahan untuk mengizinkan download
    chrome_options.add_argument('--no-sandbox')
    chrome_options.add_argument('--disable-dev-shm-usage')
    chrome_options.add_argument('--disable-gpu')
    chrome_options.add_argument('--disable-web-security')
    chrome_options.add_argument('--allow-running-insecure-content')
    chrome_options.add_argument('--disable-features=IsolateOrigins,site-per-process,DownloadBubble,DownloadBubbleV2')
    chrome_options.add_argument('--disable-site-isolation-trials')
    # chrome_options.add_argument('--headless')  # Dinonaktifkan sementara untuk debugging
    chrome_options.add_argument('--window-size=1920,1080')
    
    driver = webdriver.Chrome(options=chrome_options)
    
    # Gunakan CDP untuk mengizinkan download
    driver.execute_cdp_cmd("Page.setDownloadBehavior", {
        "behavior": "allow",
        "downloadPath": download_dir
    })
    
    login(driver)
    navigate_to_report(driver)
    isi_form(driver, tgl_box)
    school_names, unit_input = get_school_names(driver, unit_name)
    print("Daftar sekolah yang ditemukan:", school_names)
    for school_name in school_names:
        try:
            download_excel_helper(driver, unit_input, school_name, download_dir)
        except Exception as e:
            print(f"Error saat download {school_name}: {e}")
    if not CLI_MODE:
        input("Tekan Enter untuk menutup browser...")
    driver.quit()
    if CLI_MODE and os.environ.get('KIB_JOB_DIR'):
        try:
            job_dir = os.environ.get('KIB_JOB_DIR')
            download_dir = os.environ.get('KIB_DOWNLOAD_DIR') or os.path.join(os.path.expanduser("~"), "Downloads")
            files = glob.glob(os.path.join(download_dir, "*.xls"))
            if files:
                open(os.path.join(job_dir, 'done.flag'), 'w').write('done')
            else:
                status_path = os.path.join(job_dir, 'status.json')
                job_id = os.path.basename(job_dir)
                status = {'job_id': job_id, 'status': 'error', 'meta': {'message': 'Tidak ada file terunduh'}, 'updated_at': int(time.time())}
                try:
                    open(status_path, 'w').write(json.dumps(status))
                except:
                    pass
        except:
            pass

def main():
    if CLI_MODE:
        k = CLI_KIB
        if k == "A":
            run_kib_a()
        elif k == "B":
            run_kib_b()
        elif k == "C":
            run_kib_c()
        elif k == "D":
            run_kib_d()
        elif k == "E":
            run_kib_e()
        elif k == "F":
            run_kib_f()
        else:
            run_kib_a()
        return
    root = tk.Tk()
    root.title("KIB Automation Master")
    root.geometry("320x180")
    tk.Label(root, text="Pilih Jenis KIB yang akan dijalankan:").pack(pady=10)
    kib_options = ["KIB A", "KIB B", "KIB C", "KIB D", "KIB E", "KIB F"]
    kib_combo = ttk.Combobox(root, values=kib_options, state="readonly")
    kib_combo.current(0)
    kib_combo.pack(pady=10)
    def on_run():
        selected = kib_combo.get()
        root.destroy()
        if selected == "KIB A":
            run_kib_a()
        elif selected == "KIB B":
            run_kib_b()
        elif selected == "KIB C":
            run_kib_c()
        elif selected == "KIB D":
            run_kib_d()
        elif selected == "KIB E":
            run_kib_e()
        elif selected == "KIB F":
            run_kib_f()
    run_btn = tk.Button(root, text="Jalankan", command=on_run)
    run_btn.pack(pady=20)
    root.mainloop()

if __name__ == "__main__":
    main()
