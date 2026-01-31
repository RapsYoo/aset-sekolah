import time
import glob
import os
import argparse
import sys
import json
from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select, WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.action_chains import ActionChains

# Flush stdout for real-time PHP streaming
def log(message, status="info"):
    print(json.dumps({"status": status, "message": message}), flush=True)

def setup_driver(download_dir):
    chrome_options = Options()
    chrome_options.add_argument('--headless')
    chrome_options.add_argument('--no-sandbox')
    chrome_options.add_argument('--disable-dev-shm-usage')
    chrome_options.add_argument('--window-size=1920,1080')

    prefs = {
        "download.default_directory": os.path.abspath(download_dir),
        "download.prompt_for_download": False,
        "download.directory_upgrade": True,
        "safebrowsing.enabled": True
    }
    chrome_options.add_experimental_option("prefs", prefs)

    driver = webdriver.Chrome(options=chrome_options)
    return driver

def login(driver):
    log("Membuka halaman login...")
    driver.get("https://simbakda.sulselprov.go.id/index.php/welcome/login")
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.NAME, "username")))
    driver.find_element(By.NAME, "username").send_keys("DISDIK2023")
    driver.find_element(By.NAME, "password").send_keys("RAHMATGANTENG")
    Select(driver.find_element(By.NAME, "ta")).select_by_visible_text("2025")
    WebDriverWait(driver, 10).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "button[type='submit']")))
    driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()
    log("Login berhasil.")

def navigate_common(driver, menu_items):
    log("Navigasi ke menu laporan...")
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, "Laporan-Laporan")))
    laporan_menu = driver.find_element(By.LINK_TEXT, "Laporan-Laporan")
    ActionChains(driver).move_to_element(laporan_menu).click(laporan_menu).perform()

    for item in menu_items:
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.LINK_TEXT, item)))
        menu_elem = driver.find_element(By.LINK_TEXT, item)
        # Scroll to element to ensure visibility
        driver.execute_script("arguments[0].scrollIntoView();", menu_elem)
        time.sleep(0.5)
        try:
            menu_elem.click()
        except:
            ActionChains(driver).move_to_element(menu_elem).click(menu_elem).perform()
        log(f"Menu '{item}' diklik.")

def get_school_names(driver, unit_name):
    log(f"Mencari unit: {unit_name}")
    # Locator might vary slightly by KIB, but looking at source, mostly generic
    # Attempting robust locator
    xpath_input = "//input[contains(@class,'combo-text') and @autocomplete='off']"

    # Wait for the input that usually holds unit name
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.XPATH, xpath_input)))
    inputs = driver.find_elements(By.XPATH, xpath_input)

    # Usually the first or specific one. Based on script:
    # KIB F uses: "//*[@id='div_skpd']/table/tbody/tr/td[3]/span/input[1]"
    # Others use: "//td[contains(.,'UNIT')]/following-sibling::td//input..."

    # Let's try to find the one associated with "UNIT" label or ID
    unit_input = None
    for inp in inputs:
        if inp.is_displayed():
            unit_input = inp
            break

    if not unit_input:
        # Fallback to KIB F style specific path if needed, or specific index
        if len(inputs) > 0:
            unit_input = inputs[0]

    if not unit_input:
        raise Exception("Input Unit tidak ditemukan")

    unit_input.clear()
    unit_input.send_keys(unit_name)
    time.sleep(3) # Wait for filtering

    school_rows = driver.find_elements(By.XPATH, "//tr[contains(@class,'datagrid-row')]")
    school_names = []

    for row in school_rows:
        try:
            # Try to get text from columns.
            # Usually cell[1] is name based on original script
            cells = row.find_elements(By.XPATH, ".//div[contains(@class,'datagrid-cell')]")
            if len(cells) >= 2:
                name = cells[1].text.strip()
                if name:
                    school_names.append(name)
        except:
            pass

    # Remove duplicates
    school_names = list(set(school_names))
    log(f"Ditemukan {len(school_names)} sekolah.")
    return school_names, unit_input

def download_excel(driver, unit_input, school_name, download_dir):
    log(f"Memproses: {school_name}")
    unit_input.clear()
    unit_input.send_keys(school_name)
    time.sleep(2)

    # Select row
    try:
        xpath_row = f"//tr[contains(@class,'datagrid-row') and .//div[contains(text(), '{school_name}')]]"
        WebDriverWait(driver, 5).until(EC.presence_of_element_located((By.XPATH, xpath_row)))
        school_row = driver.find_element(By.XPATH, xpath_row)
        school_row.click()
    except:
        log(f"Baris untuk {school_name} tidak ditemukan atau tidak dapat diklik.", "warning")
        return None

    time.sleep(1)

    files_before = set(glob.glob(os.path.join(download_dir, "*.xls")))

    try:
        excel_btn = driver.find_element(By.CSS_SELECTOR, "span.l-btn-text.icon-excel")
        excel_btn.click()
    except:
        log("Tombol Excel tidak ditemukan.", "error")
        return None

    log("Menunggu download...")
    max_wait = 60
    waited = 0
    downloaded_file = None

    while waited < max_wait:
        files_after = set(glob.glob(os.path.join(download_dir, "*.xls")))
        new_files = files_after - files_before
        if new_files:
            downloaded_file = max(new_files, key=os.path.getctime)
            # Wait for download to finish (check if size is stable or not .crdownload)
            if not downloaded_file.endswith('.crdownload'):
                break
        time.sleep(1)
        waited += 1

    if downloaded_file:
        safe_name = "".join(c for c in school_name if c not in '\/:*?"<>|')
        new_name = f"{safe_name}.xls"
        new_path = os.path.join(download_dir, new_name)

        # Remove existing if any
        if os.path.exists(new_path):
            os.remove(new_path)

        os.rename(downloaded_file, new_path)
        log(f"Berhasil download: {new_name}", "success")
        return new_name
    else:
        log("Timeout menunggu download.", "error")
        return None

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--kib', required=True, help='KIB Type (A-F)')
    parser.add_argument('--unit', required=True, help='Unit Name')
    parser.add_argument('--date', required=True, help='Date (YYYY-MM-DD)')
    parser.add_argument('--type', help='Asset Type (1, 2, 3)')
    parser.add_argument('--code', help='Code Type (1, 2)')
    parser.add_argument('--output', required=True, help='Output Directory')

    args = parser.parse_args()

    if not os.path.exists(args.output):
        os.makedirs(args.output)

    driver = setup_driver(args.output)

    try:
        login(driver)

        # Navigation Map
        menus = {
            'A': ["Inventaris Barang", "Aset Tetap", "Kib At", "Tanah"],
            'B': ["Inventaris Barang", "Aset Tetap", "Kib At", "Peralatan & Mesin"],
            'C': ["Inventaris Barang", "Aset Tetap", "Kib At", "Gedung & Bangunan"],
            'D': ["Inventaris Barang", "Aset Tetap", "Kib At", "Jalan Irigasi & Jaringan"],
            'E': ["Inventaris Barang", "Aset Tetap", "Kib At", "At Lainnya"],
            'F': ["Inventaris Barang", "Aset Tetap", "Kib At", "Kdp"]
        }

        navigate_common(driver, menus[args.kib])

        # Fill Form
        log("Mengisi filter...")

        # Wait for form to be ready
        WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "input.combo-text.validatebox-text")))

        # Handle specific fields per KIB

        # Input 1.01.01.00 (Common)
        input_box = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text")
        input_box.clear()
        input_box.send_keys("1.01.01.00")
        time.sleep(0.5)
        input_box.send_keys("\ue007") # Enter

        # Jenis Kode (C & D)
        if args.kib in ['C', 'D'] and args.code:
            try:
                WebDriverWait(driver, 5).until(EC.presence_of_element_located((By.ID, "jns_kode")))
                Select(driver.find_element(By.ID, "jns_kode")).select_by_value(args.code)
                log(f"Jenis kode diset ke: {args.code}")
            except:
                log("Input Jenis Kode tidak ditemukan (mungkin tidak diperlukan)", "warning")

        # Format Radio (B, C, D, E)
        if args.kib in ['B', 'C', 'D', 'E']:
            try:
                xpath_radio = "//input[@type='radio' and @name='format' and @value='fa']"
                WebDriverWait(driver, 5).until(EC.presence_of_element_located((By.XPATH, xpath_radio)))
                radio = driver.find_element(By.XPATH, xpath_radio)
                if not radio.is_selected():
                    radio.click()
            except:
                pass

        # Date (Common) - Locator might vary slightly but usually width: 120px
        try:
            tgl_box = driver.find_element(By.CSS_SELECTOR, "input.combo-text.validatebox-text[style*='width: 120px']")
            tgl_box.clear()
            tgl_box.send_keys(args.date)
        except:
            log("Input tanggal sulit ditemukan, mencoba input kedua...", "warning")
            # Fallback logic could go here

        # Asset Type (A, B, C, D, E)
        if args.kib in ['A', 'B', 'C', 'D', 'E'] and args.type:
            aset_map = {"1": "jenis_aset_1", "2": "jenis_aset_2", "3": "jenis_aset_3"}
            aset_id = aset_map.get(args.type, "jenis_aset_1")
            try:
                driver.find_element(By.ID, aset_id).click()
            except:
                log(f"Radio jenis aset {aset_id} tidak ditemukan", "warning")

        # Process Schools
        school_names, unit_input = get_school_names(driver, args.unit)

        downloaded = []
        for school in school_names:
            fname = download_excel(driver, unit_input, school, args.output)
            if fname:
                downloaded.append(fname)

        log(f"Selesai. Total file terunduh: {len(downloaded)}", "done")
        print(json.dumps({"status": "result", "files": downloaded}))

    except Exception as e:
        log(f"Critical Error: {str(e)}", "error")
    finally:
        driver.quit()

if __name__ == "__main__":
    main()
