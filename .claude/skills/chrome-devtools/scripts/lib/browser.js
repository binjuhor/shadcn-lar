/**
 * Shared browser utilities for Chrome DevTools scripts
 * Supports persistent browser sessions via WebSocket endpoint file
 */
import puppeteer from 'puppeteer';
import debug from 'debug';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const log = debug('chrome-devtools:browser');

// Session file stores WebSocket endpoint for browser reuse across processes
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const SESSION_FILE = path.join(__dirname, '..', '.browser-session.json');

let browserInstance = null;
let pageInstance = null;

/**
 * Read session info from file
 */
function readSession() {
  try {
    if (fs.existsSync(SESSION_FILE)) {
      const data = JSON.parse(fs.readFileSync(SESSION_FILE, 'utf8'));
      // Check if session is not too old (max 1 hour)
      if (Date.now() - data.timestamp < 3600000) {
        return data;
      }
    }
  } catch (e) {
    log('Failed to read session:', e.message);
  }
  return null;
}

/**
 * Write session info to file
 */
function writeSession(wsEndpoint) {
  try {
    fs.writeFileSync(SESSION_FILE, JSON.stringify({
      wsEndpoint,
      timestamp: Date.now()
    }));
  } catch (e) {
    log('Failed to write session:', e.message);
  }
}

/**
 * Clear session file
 */
function clearSession() {
  try {
    if (fs.existsSync(SESSION_FILE)) {
      fs.unlinkSync(SESSION_FILE);
    }
  } catch (e) {
    log('Failed to clear session:', e.message);
  }
}

/**
 * Launch or connect to browser
 * If a session file exists with valid wsEndpoint, connects to existing browser
 * Otherwise launches new browser and saves wsEndpoint for future connections
 */
export async function getBrowser(options = {}) {
  // If we already have a connected browser in this process, reuse it
  if (browserInstance && browserInstance.isConnected()) {
    log('Reusing existing browser instance from process');
    return browserInstance;
  }

  // Try to connect to existing browser from session file
  const session = readSession();
  if (session && session.wsEndpoint) {
    try {
      log('Attempting to connect to existing browser session');
      browserInstance = await puppeteer.connect({
        browserWSEndpoint: session.wsEndpoint
      });
      log('Connected to existing browser');
      return browserInstance;
    } catch (e) {
      log('Failed to connect to existing browser:', e.message);
      clearSession();
    }
  }

  // Connect via provided wsEndpoint or browserUrl
  if (options.wsEndpoint || options.browserUrl) {
    log('Connecting to browser via provided endpoint');
    browserInstance = await puppeteer.connect({
      browserWSEndpoint: options.wsEndpoint,
      browserURL: options.browserUrl
    });
    return browserInstance;
  }

  // Launch new browser
  const launchOptions = {
    headless: options.headless !== false,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--ignore-certificate-errors',
      '--allow-insecure-localhost',
      ...(options.args || [])
    ],
    defaultViewport: options.viewport || {
      width: 1920,
      height: 1080
    },
    ...options
  };

  log('Launching new browser');
  browserInstance = await puppeteer.launch(launchOptions);

  // Save wsEndpoint for future connections
  const wsEndpoint = browserInstance.wsEndpoint();
  writeSession(wsEndpoint);
  log('Browser launched, session saved');

  return browserInstance;
}

/**
 * Get current page or create new one
 */
export async function getPage(browser) {
  if (pageInstance && !pageInstance.isClosed()) {
    log('Reusing existing page');
    return pageInstance;
  }

  const pages = await browser.pages();
  if (pages.length > 0) {
    pageInstance = pages[0];
  } else {
    pageInstance = await browser.newPage();
  }

  return pageInstance;
}

/**
 * Close browser and clear session
 */
export async function closeBrowser() {
  if (browserInstance) {
    await browserInstance.close();
    browserInstance = null;
    pageInstance = null;
    clearSession();
    log('Browser closed, session cleared');
  }
}

/**
 * Disconnect from browser without closing it
 * Use this to keep browser running for future script executions
 */
export async function disconnectBrowser() {
  if (browserInstance) {
    browserInstance.disconnect();
    browserInstance = null;
    pageInstance = null;
    log('Disconnected from browser (browser still running)');
  }
}

/**
 * Parse command line arguments
 */
export function parseArgs(argv, options = {}) {
  const args = {};

  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];

    if (arg.startsWith('--')) {
      const key = arg.slice(2);
      const nextArg = argv[i + 1];

      if (nextArg && !nextArg.startsWith('--')) {
        args[key] = nextArg;
        i++;
      } else {
        args[key] = true;
      }
    }
  }

  return args;
}

/**
 * Output JSON result
 */
export function outputJSON(data) {
  console.log(JSON.stringify(data, null, 2));
}

/**
 * Output error
 */
export function outputError(error) {
  console.error(JSON.stringify({
    success: false,
    error: error.message,
    stack: error.stack
  }, null, 2));
  process.exit(1);
}
