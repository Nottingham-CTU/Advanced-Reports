# Generated from Selenium IDE
# Test name: t16 PDF report view/download
import pytest
import time
import json
from selenium import webdriver
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support import expected_conditions
from selenium.webdriver.support.wait import WebDriverWait

class Test_16_PDF_report_view_download:
  def setup_method(self, method):
    self.driver = self.selectedBrowser
    self.vars = {}
  def teardown_method(self, method):
    self.driver.quit()

  def test_16_PDF_report_view_download(self):
    self.driver.get("http://127.0.0.1/")
    self.driver.find_element(By.LINK_TEXT, "My Projects").click()
    assert len(self.driver.find_elements(By.XPATH, "//*[@id=\"table-proj_table\"][contains(.,'Advanced Reports Test')]")) > 0
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports Test").click()
    self.driver.find_element(By.LINK_TEXT, "Advanced Reports").click()
    self.driver.execute_script("$.get( $('a:contains(\"PDF Report\")').attr('href'), function(data,status,xhr){if(data.indexOf('%PDF')==0&&xhr.getResponseHeader('Content-Type')=='application/pdf'){document.body.setAttribute('data-pdf-ok','1')};document.body.setAttribute('data-pdf','1')} )")
    WebDriverWait(self.driver, 30).until(expected_conditions.presence_of_element_located((By.CSS_SELECTOR, "body[data-pdf]")))
    self.vars["pdfDownloadOK"] = self.driver.execute_script("return ''+$('body[data-pdf-ok]').length")
    assert(self.vars["pdfDownloadOK"] == "1")
