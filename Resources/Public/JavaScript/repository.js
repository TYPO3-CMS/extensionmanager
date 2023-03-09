/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */
import $ from"jquery";import NProgress from"nprogress";import Modal from"@typo3/backend/modal.js";import Notification from"@typo3/backend/notification.js";import Severity from"@typo3/backend/severity.js";import Tablesort from"tablesort";import"@typo3/backend/input/clearable.js";import AjaxRequest from"@typo3/core/ajax/ajax-request.js";import RegularEvent from"@typo3/core/event/regular-event.js";class Repository{constructor(){this.downloadPath="",this.getDependencies=async e=>{const t=await e.resolve();NProgress.done(),t.hasDependencies?Modal.confirm(t.title,$(t.message),Severity.info,[{text:TYPO3.lang["button.cancel"],active:!0,btnClass:"btn-default",trigger:()=>{Modal.dismiss()}},{text:TYPO3.lang["button.resolveDependencies"],btnClass:"btn-primary",trigger:()=>{this.getResolveDependenciesAndInstallResult(t.url+"&downloadPath="+this.downloadPath),Modal.dismiss()}}]):t.hasErrors?Notification.error(t.title,t.message,15):this.getResolveDependenciesAndInstallResult(t.url+"&downloadPath="+this.downloadPath)}}initDom(){NProgress.configure({parent:".module-loading-indicator",showSpinner:!1});const e=document.getElementById("terVersionTable"),t=document.getElementById("terSearchTable");null!==e&&new Tablesort(e),null!==t&&new Tablesort(t),this.bindDownload(),this.bindSearchFieldResetter()}bindDownload(){new RegularEvent("click",((e,t)=>{e.preventDefault();const n=t.closest("form"),s=n.dataset.href;this.downloadPath=n.querySelector("input.downloadPath:checked").value,NProgress.start(),new AjaxRequest(s).get().then(this.getDependencies)})).delegateTo(document,".downloadFromTer form.download button[type=submit]")}getResolveDependenciesAndInstallResult(e){NProgress.start(),new AjaxRequest(e).get().then((async e=>{const t=await e.raw().json();if(t.errorCount>0){const e=Modal.confirm(t.errorTitle,$(t.errorMessage),Severity.error,[{text:TYPO3.lang["button.cancel"],active:!0,btnClass:"btn-default",trigger:()=>{Modal.dismiss()}},{text:TYPO3.lang["button.resolveDependenciesIgnore"],btnClass:"btn-danger disabled t3js-dependencies",trigger:e=>{$(e.currentTarget).hasClass("disabled")||(this.getResolveDependenciesAndInstallResult(t.skipDependencyUri),Modal.dismiss())}}]);e.addEventListener("typo3-modal-shown",(()=>{const t=e.querySelector(".t3js-dependencies");e.querySelector('input[name="unlockDependencyIgnoreButton"]').addEventListener("change",(e=>{e.currentTarget.checked?t?.classList.remove("disabled"):t?.classList.add("disabled")}))}))}else{let e=TYPO3.lang["extensionList.dependenciesResolveDownloadSuccess.message"+t.installationTypeLanguageKey].replace(/\{0\}/g,t.extension);e+="\n"+TYPO3.lang["extensionList.dependenciesResolveDownloadSuccess.header"]+": ";for(const[n,s]of Object.entries(t.result)){e+="\n\n"+TYPO3.lang["extensionList.dependenciesResolveDownloadSuccess.item"]+" "+n+": ";for(const t of s)e+="\n* "+t}Notification.info(TYPO3.lang["extensionList.dependenciesResolveFlashMessage.title"+t.installationTypeLanguageKey].replace(/\{0\}/g,t.extension),e,15),top.TYPO3.ModuleMenu.App.refreshMenu()}})).finally((()=>{NProgress.done()}))}bindSearchFieldResetter(){let e;if(null!==(e=document.querySelector('.typo3-extensionmanager-searchTerForm input[type="text"]'))){const t=""!==e.value;e.clearable({onClear:e=>{t&&e.closest("form").submit()}})}}}export default Repository;