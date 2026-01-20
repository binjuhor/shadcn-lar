#!/bin/bash
# scout-block.sh - Bash implementation for blocking heavy directories
# Reads patterns from .ckignore file (defaults: node_modules, __pycache__, .git, dist, build)
#
# Blocking Rules:
# - File paths: Blocks any file_path/path/pattern containing blocked directories
# - Bash commands: Blocks directory access (cd, ls, cat, etc.) but ALLOWS build commands
#   - Blocked: cd node_modules, ls build/, cat dist/file.js
#   - Allowed: npm build, pnpm build, yarn build, npm run build

# Read stdin
INPUT=$(cat)

# Validate input not empty
if [ -z "$INPUT" ]; then
  echo "ERROR: Empty input" >&2
  exit 2
fi

# Determine script directory for .ckignore lookup
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Look for .ckignore in .claude/ folder (2 levels up from .claude/hooks/scout-block/)
CLAUDE_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
CKIGNORE_FILE="$CLAUDE_DIR/.ckignore"

# Parse JSON and extract all relevant parameters using Node.js
# Output format: "ALLOWED" or "BLOCKED:pattern1,pattern2,..."
CHECK_RESULT=$(echo "$INPUT" | CKIGNORE_FILE="$CKIGNORE_FILE" node -e "
const fs = require('fs');
const path = require('path');

try {
  const input = fs.readFileSync(0, 'utf-8');
  const data = JSON.parse(input);

  if (!data.tool_input || typeof data.tool_input !== 'object') {
    // If JSON structure is invalid, allow operation with warning
    console.error('WARN: Invalid JSON structure, allowing operation');
    console.log('ALLOWED');
    process.exit(0);
  }

  const toolInput = data.tool_input;

  // Read patterns from .ckignore file
  const ckignorePath = process.env.CKIGNORE_FILE;
  let blockedPatterns = ['node_modules', '__pycache__', '.git', 'dist', 'build']; // defaults

  if (ckignorePath && fs.existsSync(ckignorePath)) {
    const content = fs.readFileSync(ckignorePath, 'utf-8');
    const patterns = content
      .split('\n')
      .map(line => line.trim())
      .filter(line => line && !line.startsWith('#'));
    if (patterns.length > 0) {
      blockedPatterns = patterns;
    }
  }

  // Escape special regex characters and build dynamic patterns
  const escapeRegex = (str) => str.replace(/[.*+?^\${}()|[\]\\\\]/g, '\\\\$&');
  const escapedPatterns = blockedPatterns.map(escapeRegex);
  const patternGroup = escapedPatterns.join('|');

  // Pattern for directory paths (used for file_path, path, pattern)
  const blockedDirPattern = new RegExp('(^|/|\\\\s)(' + patternGroup + ')(/|\$|\\\\s)');

  // Pattern for Bash commands - only block directory access, not build commands
  // Blocks: cd node_modules, ls build/, cat dist/file.js
  // Allows: npm build, pnpm build, yarn build, npm run build
  const blockedBashPattern = new RegExp(
    '(cd\\\\s+|ls\\\\s+|cat\\\\s+|rm\\\\s+|cp\\\\s+|mv\\\\s+|find\\\\s+)(' + patternGroup + ')(/|\$|\\\\s)|' +
    '(\\\\s|^|/)(' + patternGroup + ')/'
  );

  // Check file path parameters (strict blocking)
  const fileParams = [
    toolInput.file_path,    // Read, Edit, Write tools
    toolInput.path,         // Grep, Glob tools
    toolInput.pattern       // Glob, Grep tools
  ];

  for (const param of fileParams) {
    if (param && typeof param === 'string' && blockedDirPattern.test(param)) {
      // Output blocked with pattern list for dynamic error message
      console.log('BLOCKED:' + blockedPatterns.join(','));
      process.exit(0);
    }
  }

  // Check Bash command (selective blocking - only directory access)
  if (toolInput.command && typeof toolInput.command === 'string') {
    if (blockedBashPattern.test(toolInput.command)) {
      // Output blocked with pattern list for dynamic error message
      console.log('BLOCKED:' + blockedPatterns.join(','));
      process.exit(0);
    }
  }

  console.log('ALLOWED');
} catch (error) {
  // If JSON parsing fails, allow operation with warning (fail-open approach)
  // This prevents hook configuration issues from blocking all operations
  console.error('WARN: JSON parse failed, allowing operation -', error.message);
  console.log('ALLOWED');
  process.exit(0);
}
")

# Check if parsing failed
if [ $? -ne 0 ]; then
  exit 2
fi

# Check result - now handles "BLOCKED:patterns" format
if [[ "$CHECK_RESULT" == BLOCKED:* ]]; then
  # Extract patterns from result (format: "BLOCKED:pattern1,pattern2,...")
  PATTERNS="${CHECK_RESULT#BLOCKED:}"
  echo "ERROR: Blocked directory pattern ($PATTERNS)" >&2
  exit 2
fi

# Allow command (exit 0)
exit 0