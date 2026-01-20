#!/usr/bin/env node
'use strict';

/**
 * Custom Claude Code statusline for Node.js
 * Cross-platform support: Windows, macOS, Linux
 * Theme: detailed | Colors: true | Features: directory, git, model, usage, session, tokens
 * No external dependencies - uses only Node.js built-in modules
 */

const { stdin, stdout, env } = require('process');
const { execSync } = require('child_process');
const os = require('os');

// Configuration
const USE_COLOR = !env.NO_COLOR && stdout.isTTY;

// Color helpers
const color = (code) => USE_COLOR ? `\x1b[${code}m` : '';
const reset = () => USE_COLOR ? '\x1b[0m' : '';

// Color definitions
const DirColor = color('1;36');      // cyan
const GitColor = color('1;32');      // green
const ModelColor = color('1;35');    // magenta
const VersionColor = color('1;33');  // yellow
const UsageColor = color('1;35');    // magenta
const CostColor = color('1;36');     // cyan
const Reset = reset();

/**
 * Safe command execution wrapper
 */
function exec(cmd) {
    try {
        return execSync(cmd, {
            encoding: 'utf8',
            stdio: ['pipe', 'pipe', 'ignore'],
            windowsHide: true
        }).trim();
    } catch (err) {
        return '';
    }
}

/**
 * Convert ISO8601 timestamp to Unix epoch
 */
function toEpoch(timestamp) {
    try {
        const date = new Date(timestamp);
        return Math.floor(date.getTime() / 1000);
    } catch (err) {
        return 0;
    }
}

/**
 * Format epoch timestamp as HH:mm
 */
function formatTimeHM(epoch) {
    try {
        const date = new Date(epoch * 1000);
        const hours = date.getHours().toString().padStart(2, '0');
        const minutes = date.getMinutes().toString().padStart(2, '0');
        return `${hours}:${minutes}`;
    } catch (err) {
        return '00:00';
    }
}

/**
 * Get session color based on remaining percentage
 */
function getSessionColor(sessionPercent) {
    if (!USE_COLOR) return '';

    const remaining = 100 - sessionPercent;
    if (remaining <= 10) {
        return '\x1b[1;31m';  // red
    } else if (remaining <= 25) {
        return '\x1b[1;33m';  // yellow
    } else {
        return '\x1b[1;32m';  // green
    }
}

/**
 * Expand home directory to ~
 */
function expandHome(path) {
    const homeDir = os.homedir();
    if (path.startsWith(homeDir)) {
        return path.replace(homeDir, '~');
    }
    return path;
}

/**
 * Read stdin asynchronously
 */
async function readStdin() {
    return new Promise((resolve, reject) => {
        const chunks = [];
        stdin.setEncoding('utf8');

        stdin.on('data', (chunk) => {
            chunks.push(chunk);
        });

        stdin.on('end', () => {
            resolve(chunks.join(''));
        });

        stdin.on('error', (err) => {
            reject(err);
        });
    });
}

/**
 * Main function
 */
async function main() {
    try {
        // Read and parse JSON input
        const input = await readStdin();
        if (!input.trim()) {
            console.error('No input provided');
            process.exit(1);
        }

        const data = JSON.parse(input);

        // Extract basic information
        let currentDir = 'unknown';
        if (data.workspace?.current_dir) {
            currentDir = data.workspace.current_dir;
        } else if (data.cwd) {
            currentDir = data.cwd;
        }
        currentDir = expandHome(currentDir);

        const modelName = data.model?.display_name || 'Claude';
        const modelVersion = data.model?.version && data.model.version !== 'null' ? data.model.version : '';

        // Git branch detection
        let gitBranch = '';
        const gitCheck = exec('git rev-parse --git-dir');
        if (gitCheck) {
            gitBranch = exec('git branch --show-current');
            if (!gitBranch) {
                gitBranch = exec('git rev-parse --short HEAD');
            }
        }

        // Native Claude Code data integration
        let sessionText = '';
        let sessionPercent = 0;
        let costUSD = '';
        let linesAdded = 0;
        let linesRemoved = 0;
        const billingMode = env.CLAUDE_BILLING_MODE || 'api';

        // Extract native cost data from Claude Code
        costUSD = data.cost?.total_cost_usd || '';
        linesAdded = data.cost?.total_lines_added || 0;
        linesRemoved = data.cost?.total_lines_removed || 0;

        // Session timer - parse local transcript JSONL (zero external dependencies)
        const transcriptPath = data.transcript_path;

        if (transcriptPath) {
            try {
                const fs = require('fs');
                if (fs.existsSync(transcriptPath)) {
                    const content = fs.readFileSync(transcriptPath, 'utf8');
                    const lines = content.split('\n').filter(l => l.trim());

                    // Find first API call with usage data
                    let firstApiCall = null;
                    for (const line of lines) {
                        try {
                            const entry = JSON.parse(line);
                            if (entry.usage && entry.timestamp) {
                                firstApiCall = entry.timestamp;
                                break;
                            }
                        } catch (e) {
                            continue;
                        }
                    }

                    if (firstApiCall) {
                        // Calculate 5-hour billing block (Anthropic windows)
                        const now = new Date();
                        const currentUtcHour = now.getUTCHours();
                        const blockStart = Math.floor(currentUtcHour / 5) * 5;
                        let blockEnd = blockStart + 5;

                        // Handle day wraparound
                        let blockEndDate = new Date(now);
                        if (blockEnd >= 24) {
                            blockEnd -= 24;
                            blockEndDate.setUTCDate(blockEndDate.getUTCDate() + 1);
                        }
                        blockEndDate.setUTCHours(blockEnd, 0, 0, 0);

                        const nowSec = Math.floor(Date.now() / 1000);
                        const blockEndSec = Math.floor(blockEndDate.getTime() / 1000);
                        const remaining = blockEndSec - nowSec;

                        if (remaining > 0 && remaining < 18000) {
                            const rh = Math.floor(remaining / 3600);
                            const rm = Math.floor((remaining % 3600) / 60);
                            const blockEndLocal = formatTimeHM(blockEndSec);
                            sessionText = `${rh}h ${rm}m until reset at ${blockEndLocal}`;
                            sessionPercent = Math.floor((18000 - remaining) * 100 / 18000);
                        }
                    }
                }
            } catch (err) {
                // Silent fail - transcript not readable
            }
        }

        // Render statusline
        let output = '';

        // Directory
        output += `📁 ${DirColor}${currentDir}${Reset}`;

        // Git branch
        if (gitBranch) {
            output += `  🌿 ${GitColor}${gitBranch}${Reset}`;
        }

        // Model
        output += `  🤖 ${ModelColor}${modelName}${Reset}`;

        // Model version
        if (modelVersion) {
            output += `  🏷️ ${VersionColor}${modelVersion}${Reset}`;
        }

        // Session time
        if (sessionText) {
            const sessionColorCode = getSessionColor(sessionPercent);
            output += `  ⌛ ${sessionColorCode}${sessionText}${Reset}`;
        }

        // Cost (only show for API billing mode)
        if (billingMode === 'api' && costUSD && /^\d+(\.\d+)?$/.test(costUSD.toString())) {
            const costUSDNum = parseFloat(costUSD);
            output += `  💵 ${CostColor}$${costUSDNum.toFixed(4)}${Reset}`;
        }

        // Lines changed
        if ((linesAdded > 0 || linesRemoved > 0)) {
            const linesColor = color('1;32');  // green
            output += `  📝 ${linesColor}+${linesAdded} -${linesRemoved}${Reset}`;
        }

        console.log(output);
    } catch (err) {
        console.error('Error:', err.message);
        process.exit(1);
    }
}

main().catch(err => {
    console.error('Fatal error:', err);
    process.exit(1);
});
