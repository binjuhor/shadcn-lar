#!/usr/bin/env node

/**
 * Test script for scout-block.sh hook
 * Tests various tool inputs to verify blocking logic
 */

const { execSync } = require('child_process');
const path = require('path');

const testCases = [
  // Directory access - should be BLOCKED
  {
    name: 'Bash: ls node_modules',
    input: { tool_name: 'Bash', tool_input: { command: 'ls node_modules' } },
    expected: 'BLOCKED'
  },
  {
    name: 'Bash: cd build',
    input: { tool_name: 'Bash', tool_input: { command: 'cd build' } },
    expected: 'BLOCKED'
  },
  {
    name: 'Bash: cat dist/bundle.js',
    input: { tool_name: 'Bash', tool_input: { command: 'cat dist/bundle.js' } },
    expected: 'BLOCKED'
  },
  {
    name: 'Grep with node_modules path',
    input: { tool_name: 'Grep', tool_input: { pattern: 'test', path: 'node_modules' } },
    expected: 'BLOCKED'
  },
  {
    name: 'Glob with node_modules pattern',
    input: { tool_name: 'Glob', tool_input: { pattern: '**/node_modules/**' } },
    expected: 'BLOCKED'
  },
  {
    name: 'Read with node_modules file_path',
    input: { tool_name: 'Read', tool_input: { file_path: 'node_modules/package.json' } },
    expected: 'BLOCKED'
  },

  // Build commands - should be ALLOWED
  {
    name: 'Bash: npm build',
    input: { tool_name: 'Bash', tool_input: { command: 'npm build' } },
    expected: 'ALLOWED'
  },
  {
    name: 'Bash: pnpm build',
    input: { tool_name: 'Bash', tool_input: { command: 'pnpm build' } },
    expected: 'ALLOWED'
  },
  {
    name: 'Bash: yarn build',
    input: { tool_name: 'Bash', tool_input: { command: 'yarn build' } },
    expected: 'ALLOWED'
  },
  {
    name: 'Bash: npm run build',
    input: { tool_name: 'Bash', tool_input: { command: 'npm run build' } },
    expected: 'ALLOWED'
  },
  {
    name: 'Bash: pnpm --filter web run build',
    input: { tool_name: 'Bash', tool_input: { command: 'pnpm --filter web run build 2>&1 | tail -100' } },
    expected: 'ALLOWED'
  },

  // Safe operations - should be ALLOWED
  {
    name: 'Grep with safe path',
    input: { tool_name: 'Grep', tool_input: { pattern: 'test', path: 'src' } },
    expected: 'ALLOWED'
  },
  {
    name: 'Read with safe file_path',
    input: { tool_name: 'Read', tool_input: { file_path: 'src/index.js' } },
    expected: 'ALLOWED'
  }
];

console.log('Testing scout-block.sh hook...\n');

const scriptPath = path.join(__dirname, '..', 'scout-block', 'scout-block.sh');
let passed = 0;
let failed = 0;

for (const test of testCases) {
  try {
    const input = JSON.stringify(test.input);
    const result = execSync(`bash "${scriptPath}"`, {
      input,
      encoding: 'utf-8',
      stdio: ['pipe', 'pipe', 'pipe']
    });

    const actual = 'ALLOWED';
    const success = actual === test.expected;

    if (success) {
      console.log(`✓ ${test.name}: ${actual}`);
      passed++;
    } else {
      console.log(`✗ ${test.name}: expected ${test.expected}, got ${actual}`);
      failed++;
    }
  } catch (error) {
    const actual = error.status === 2 ? 'BLOCKED' : 'ERROR';
    const success = actual === test.expected;

    if (success) {
      console.log(`✓ ${test.name}: ${actual}`);
      passed++;
    } else {
      console.log(`✗ ${test.name}: expected ${test.expected}, got ${actual}`);
      console.log(`  Error: ${error.stderr.toString().trim()}`);
      failed++;
    }
  }
}

console.log(`\nResults: ${passed} passed, ${failed} failed`);
process.exit(failed > 0 ? 1 : 0);
