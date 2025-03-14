import React from 'react';
import { createAuthTokens, ENDPOINT, getHeaders, postData } from '../apiAuth';
import { GLOBALS } from '../globals';
import _ from 'lodash';
import i18n from 'i18n-js';

import { create } from 'apisauce';
import { PATRON } from '../loadPatron';
import { popAlert } from '../../components/loadError';
import { LIBRARY } from '../loadLibrary';

const endpoint = ENDPOINT.user;

/** *******************************************************************
 * General
 ******************************************************************* **/
/**
 * Returns profile information for a given user
 * @param {string} url
 **/
export async function refreshProfile(url) {
     const postBody = await postData();
     const discovery = create({
          baseURL: url,
          timeout: GLOBALS.timeoutAverage,
          headers: getHeaders(endpoint.isPost),
          auth: createAuthTokens(),
          params: {
               linkedUsers: true,
               reload: false,
               checkIfValid: false,
          },
     });
     const response = await discovery.post(`${endpoint.url}getPatronProfile`, postBody);
     if (response.ok) {
          if (response.data.result) {
               //console.log(response.data.result.profile);
               return response.data.result.profile;
          }
     }
     return [];
}

/**
 * Returns profile information for a given user (force refresh)
 * @param {string} url
 **/
export async function reloadProfile(url) {
     const postBody = await postData();
     const discovery = create({
          baseURL: url,
          timeout: GLOBALS.timeoutAverage,
          headers: getHeaders(endpoint.isPost),
          auth: createAuthTokens(),
          params: {
               linkedUsers: true,
               reload: true,
               checkIfValid: false,
          },
     });
     const response = await discovery.post(`${endpoint.url}getPatronProfile`, postBody);
     if (response.ok) {
          if (response.data.result) {
               //console.log(response.data.result.profile);
               return response.data.result.profile;
          }
     }
     return [];
}

/**
 * Checks if the user has an active Aspen Discovery session
 * @param {string} url
 **/
export async function isLoggedIn(url) {
     const postBody = await postData();
     const discovery = create({
          baseURL: url,
          timeout: GLOBALS.timeoutFast,
          headers: getHeaders(endpoint.isPost),
          auth: createAuthTokens(),
     });
     const response = await discovery.post(`${endpoint.url}isLoggedIn`, postBody);
     if (response.ok) {
          return response.data.result;
     } else {
          console.log(response);
          return false;
     }
}

/**
 * Logout the user and end the Aspen Discovery session
 **/
export async function logoutUser(url) {
     const api = create({
          baseURL: url,
          timeout: GLOBALS.timeoutFast,
          headers: getHeaders(endpoint.isPost),
          auth: createAuthTokens(),
     });
     const response = await api.get(`${endpoint.url}logout`);
     if (response.ok) {
          return response.data;
     } else {
          console.log(response);
          return false;
     }
}

/** *******************************************************************
 * Browse Category Management
 ******************************************************************* **/
/**
 * Show a hidden browse category for a user
 * @param {string} categoryId
 * @param {string} patronId
 * @param {string} url
 **/
export async function showBrowseCategory(categoryId, patronId, url) {
     const postBody = await postData();
     const discovery = create({
          baseURL: url,
          timeout: GLOBALS.timeoutFast,
          headers: getHeaders(endpoint.isPost),
          auth: createAuthTokens(),
          params: {
               browseCategoryId: categoryId,
               patronId: patronId,
          },
     });
     const response = await discovery.post(`${endpoint.url}showBrowseCategory`, postBody);
     if (response.ok) {
          return response.data;
     } else {
          console.log(response);
          return false;
     }
}

/**
 * Dismiss a browse category for a user
 * @param {string} categoryId
 * @param {string} patronId
 * @param {string} url
 **/
export async function hideBrowseCategory(categoryId, patronId, url) {
     const postBody = await postData();
     const discovery = create({
          baseURL: url,
          timeout: GLOBALS.timeoutFast,
          headers: getHeaders(endpoint.isPost),
          auth: createAuthTokens(),
          params: {
               browseCategoryId: categoryId,
               patronId: patronId,
          },
     });
     const response = await discovery.post(`${endpoint.url}dismissBrowseCategory`, postBody);
     if (response.ok) {
          return response.data;
     } else {
          console.log(response);
          return false;
     }
}

/** *******************************************************************
 * Linked Accounts
 ******************************************************************* **/
/**
 * Return a list of accounts that the user has initiated account linking with
 * @param {string} url
 **/
export async function getLinkedAccounts(url) {
     const postBody = await postData();
     const discovery = create({
          baseURL: url,
          timeout: GLOBALS.timeoutAverage,
          headers: getHeaders(endpoint.isPost),
          auth: createAuthTokens(),
     });
     const response = await discovery.post(`${endpoint.url}getLinkedAccounts`, postBody);
     if (response.ok) {
          let accounts = [];
          if (!_.isUndefined(response.data.result.linkedAccounts)) {
               accounts = response.data.result.linkedAccounts;
               PATRON.linkedAccounts = accounts;
               return accounts;
          }
          return accounts;
     } else {
          console.log(response);
          return false;
     }
}

/**
 * Return a list of accounts that the user has been linked to by another user
 * @param {string} url
 **/
export async function getViewerAccounts(url) {
     const postBody = await postData();
     const discovery = create({
          baseURL: url,
          timeout: GLOBALS.timeoutAverage,
          headers: getHeaders(endpoint.isPost),
          auth: createAuthTokens(),
     });
     const response = await discovery.post(`${endpoint.url}getViewers`, postBody);
     if (response.ok) {
          let viewers = [];
          if (!_.isUndefined(response.data.result.linkedAccounts)) {
               viewers = response.data.result.linkedAccounts;
               PATRON.viewerAcccounts = viewers;
          }
          return viewers;
     } else {
          console.log(response);
          return false;
     }
}

/**
 * Return barcodes for a user's linked accounts
 * @param {string} url
 **/
export async function getLinkedAccountBarcodes(url) {
     const postBody = await postData();
     const discovery = create({
          baseURL: url,
          timeout: GLOBALS.timeoutFast,
          headers: getHeaders(endpoint.isPost),
          auth: createAuthTokens(),
     });
     const response = await discovery.post(`${endpoint.url}getLinkedAccountBarcodes`, postBody);
     if (response.ok) {
          //
     }
     return false;
}

/**
 * Add an account that the user wants to create a link to
 * @param {array} patronToAdd
 * @param {string} url
 **/
export async function addLinkedAccount(patronToAdd, url) {
     const postBody = await postData();
     if (_.isArray(patronToAdd)) {
          postBody.append('accountToLinkUsername', patronToAdd['username']);
          postBody.append('accountToLinkPassword', patronToAdd['password']);
     } else {
          console.log('patronToAdd credentials not provided');
          return false;
     }
     const discovery = create({
          baseURL: url,
          timeout: GLOBALS.timeoutFast,
          headers: getHeaders(endpoint.isPost),
          auth: createAuthTokens(),
     });
     const response = await discovery.post(`${endpoint.url}removeAccountLink`, postBody);
     if (response.ok) {
          let status = false;
          if (!_.isUndefined(response.data.result.success)) {
               status = response.data.result.success;
               if (status !== true) {
                    popAlert(response.data.result.title, response.data.result.message, 'success');
               } else {
                    popAlert(response.data.result.title, response.data.result.message, 'error');
               }
          }
          return status;
     } else {
          console.log(response);
          return false;
     }
}

/**
 * Remove an account that the user has created a link to
 * @param {string} patronToRemove
 * @param {string} url
 **/
export async function removeLinkedAccount(patronToRemove, url) {
     const postBody = await postData();
     const discovery = create({
          baseURL: url,
          timeout: GLOBALS.timeoutFast,
          headers: getHeaders(endpoint.isPost),
          auth: createAuthTokens(),
          params: {
               idToRemove: patronToRemove,
          },
     });
     const response = await discovery.post(`${endpoint.url}removeAccountLink`, postBody);
     if (response.ok) {
          let status = false;
          if (!_.isUndefined(response.data.result.success)) {
               status = response.data.result.success;
               if (status !== true) {
                    popAlert(response.data.result.title, response.data.result.message, 'success');
               } else {
                    popAlert(response.data.result.title, response.data.result.message, 'error');
               }
          }
          return status;
     } else {
          console.log(response);
          return false;
     }
}

/** *******************************************************************
 * Translations / Languages
 ******************************************************************* **/
/**
 * Update the user's language preference
 * @param {string} code
 * @param {string} url
 **/
export async function saveLanguage(code, url) {
     const postBody = await postData();
     const discovery = create({
          baseURL: url,
          timeout: GLOBALS.timeoutFast,
          headers: getHeaders(endpoint.isPost),
          auth: createAuthTokens(),
          params: {
               languageCode: code,
          },
     });
     const response = await discovery.post(`${endpoint.url}saveLanguage`, postBody);
     if (response.ok) {
          i18n.locale = code;
          PATRON.language = code;
          return code;
     } else {
          console.log(response);
          return false;
     }
}