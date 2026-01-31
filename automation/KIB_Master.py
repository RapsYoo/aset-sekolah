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
import os

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
		download_dir = os.path.join(os.path.expanduser("~"), "Downloads")
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
	chrome_options.add_argument('--headless')
	chrome_options.add_argument('--window-size=1920,1080')
	driver = webdriver.Chrome(options=chrome_options)
	login(driver)
	navigate_to_report(driver)
	isi_form(driver, tgl_box, jenis_aset)
	school_names, unit_input = get_school_names(driver, unit_name)
	print("Daftar sekolah yang ditemukan:", school_names)
	for school_name in school_names:
		try:
			unit_input.clear()
			unit_input.send_keys(school_name)
			time.sleep(2)
			download_excel(driver, unit_input, school_name)
		except Exception as e:
			print(f"Error saat download {school_name}: {e}")
	input("Tekan Enter untuk menutup browser...")
	driver.quit()

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
		download_dir = os.path.join(os.path.expanduser("~"), "Downloads")
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
	chrome_options.add_argument('--headless')
	chrome_options.add_argument('--window-size=1920,1080')
	driver = webdriver.Chrome(options=chrome_options)
	login(driver)
	navigate_to_report(driver)
	isi_form(driver, tgl_box, jenis_aset)
	school_names, unit_input = get_school_names(driver, unit_name)
	print("Daftar sekolah yang ditemukan:", school_names)
	for school_name in school_names:
		try:
			download_excel(driver, unit_input, school_name)
		except Exception as e:
			print(f"Error saat download {school_name}: {e}")
	input("Tekan Enter untuk menutup browser...")
	driver.quit()
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
        download_dir = os.path.join(os.path.expanduser("~"), "Downloads")
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
    chrome_options.add_argument('--headless')
    chrome_options.add_argument('--window-size=1920,1080')
    driver = webdriver.Chrome(options=chrome_options)
    login(driver)
    navigate_to_report(driver)
    isi_form(driver, tgl_box, jenis_aset, jenis_kode)  # TAMBAH PARAMETER
    school_names, unit_input = get_school_names(driver, unit_name)
    print("Daftar sekolah yang ditemukan:", school_names)
    for school_name in school_names:
        try:
            download_excel(driver, unit_input, school_name)
        except Exception as e:
            print(f"Error saat download {school_name}: {e}")
    input("Tekan Enter untuk menutup browser...")
    driver.quit()
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
        download_dir = os.path.join(os.path.expanduser("~"), "Downloads")
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
    chrome_options.add_argument('--headless')
    chrome_options.add_argument('--window-size=1920,1080')
    driver = webdriver.Chrome(options=chrome_options)
    login(driver)
    navigate_to_report(driver)
    isi_form(driver, tgl_box, jenis_aset, jenis_kode)  # TAMBAH PARAMETER
    school_names, unit_input = get_school_names(driver, unit_name)
    print("Daftar sekolah yang ditemukan:", school_names)
    for school_name in school_names:
        try:
            download_excel(driver, unit_input, school_name)
        except Exception as e:
            print(f"Error saat download {school_name}: {e}")
    input("Tekan Enter untuk menutup browser...")
    driver.quit()
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
		download_dir = os.path.join(os.path.expanduser("~"), "Downloads")
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
	chrome_options.add_argument('--headless')
	chrome_options.add_argument('--window-size=1920,1080')
	driver = webdriver.Chrome(options=chrome_options)
	login(driver)
	navigate_to_report(driver)
	isi_form(driver, tgl_box, jenis_aset)
	school_names, unit_input = get_school_names(driver, unit_name)
	print("Daftar sekolah yang ditemukan:", school_names)
	for school_name in school_names:
		try:
			download_excel(driver, unit_input, school_name)
		except Exception as e:
			print(f"Error saat download {school_name}: {e}")
	input("Tekan Enter untuk menutup browser...")
	driver.quit()
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
		download_dir = os.path.join(os.path.expanduser("~"), "Downloads")
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
	chrome_options.add_argument('--headless')
	chrome_options.add_argument('--window-size=1920,1080')
	driver = webdriver.Chrome(options=chrome_options)
	login(driver)
	navigate_to_report(driver)
	isi_form(driver, tgl_box)
	school_names, unit_input = get_school_names(driver, unit_name)
	print("Daftar sekolah yang ditemukan:", school_names)
	for school_name in school_names:
		try:
			download_excel(driver, unit_input, school_name)
		except Exception as e:
			print(f"Error saat download {school_name}: {e}")
	input("Tekan Enter untuk menutup browser...")
	driver.quit()

def main():
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
