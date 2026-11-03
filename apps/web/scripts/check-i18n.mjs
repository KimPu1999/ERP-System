/* global console, process */
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(import.meta.dirname, '..');
const source = fs.readFileSync(path.join(root, 'src/lib/i18n.tsx'), 'utf8');
const optionalEnglishSection = source.slice(source.indexOf('const optionalEnglish = {'), source.indexOf('} as const;', source.indexOf('const optionalEnglish = {')));
const optionalBurmeseSection = source.slice(source.indexOf('const optionalBurmese = {'), source.indexOf('} as const;', source.indexOf('const optionalBurmese = {')));
const platformCatalog = (names) => names.map((name) => source.slice(source.indexOf(`const ${name} = {`), source.indexOf('} as const;', source.indexOf(`const ${name} = {`))));
const platformEnglishSections = platformCatalog(['platformEnglish', 'platformAdditionalEnglish', 'platformBillingEnglish', 'platformTableEnglish', 'platformNavigationEnglish', 'platformAnalyticsEnglish', 'platformMonitoringEnglish', 'platformInvitationEnglish', 'platformManagementEnglish', 'platformManagementExtraEnglish', 'authAdditionalEnglish']);
const platformBurmeseSections = platformCatalog(['platformBurmese', 'platformAdditionalBurmese', 'platformBillingBurmese', 'platformTableBurmese', 'platformNavigationBurmese', 'platformAnalyticsBurmese', 'platformMonitoringBurmese', 'platformInvitationBurmese', 'platformManagementBurmese', 'platformManagementExtraBurmese', 'authAdditionalBurmese']);
const englishSection = source.slice(source.indexOf('  en: {'), source.indexOf("  my: {"));
const burmeseSection = source.slice(source.indexOf('  my: {'), source.indexOf('\n  },\n} as const;'));
const keyPattern = /'([^']+)'\s*:/g;
const keysIn = (value) => [...value.matchAll(keyPattern)].map((match) => match[1]);
const englishKeys = new Set([...keysIn(optionalEnglishSection), ...keysIn(englishSection), ...platformEnglishSections.flatMap(keysIn)]);
const burmeseKeys = new Set([...keysIn(optionalBurmeseSection), ...keysIn(burmeseSection), ...platformBurmeseSections.flatMap(keysIn)]);
const missingBurmese = [...englishKeys].filter((key) => !burmeseKeys.has(key));
const missingEnglish = [...burmeseKeys].filter((key) => !englishKeys.has(key));

const duplicateKeys = (keys) => [...new Set(keys.filter((key, index) => keys.indexOf(key) !== index))];
const duplicateEnglish = duplicateKeys(keysIn(englishSection));
const duplicateBurmese = duplicateKeys(keysIn(burmeseSection));

const files = [];
const visit = (directory) => {
  for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
    const fullPath = path.join(directory, entry.name);
    if (entry.isDirectory()) visit(fullPath);
    else if (/\.(tsx|ts)$/.test(entry.name) && !fullPath.endsWith('lib/i18n.tsx')) files.push(fullPath);
  }
};
visit(path.join(root, 'src'));
const allSource = files.map((file) => fs.readFileSync(file, 'utf8')).join('\n');
const referencedKeys = [...allSource.matchAll(/\bt\(\s*['"]([^'"]+)['"]/g)].map((match) => match[1]);
const unresolvedKeys = [...new Set(referencedKeys.filter((key) => !englishKeys.has(key)))];
const hardcodedCandidates = [];
const textPattern = /<(?:h[1-6]|button|label|option|p|span|th|td|a)(?:\s[^>]*)?>\s*([A-Z][^<{\n]{2,})\s*<\//g;
for (const file of files) {
  const content = fs.readFileSync(file, 'utf8');
  for (const match of content.matchAll(textPattern)) hardcodedCandidates.push(`${path.relative(root, file)}: ${match[1].trim()}`);
}

console.log(`Translation catalog: ${englishKeys.size} English / ${burmeseKeys.size} Burmese keys`);
console.log(`Referenced translation keys: ${new Set(referencedKeys).size}`);
if (missingBurmese.length) console.error(`Missing Burmese keys: ${missingBurmese.join(', ')}`);
if (missingEnglish.length) console.error(`Missing English keys: ${missingEnglish.join(', ')}`);
if (duplicateEnglish.length || duplicateBurmese.length) console.error(`Duplicate catalog keys: ${[...duplicateEnglish, ...duplicateBurmese].join(', ')}`);
if (unresolvedKeys.length) console.error(`Unresolved t() keys: ${unresolvedKeys.join(', ')}`);
if (hardcodedCandidates.length) {
  console.warn(`Hardcoded UI candidates (${hardcodedCandidates.length}; review false positives before translating):`);
  hardcodedCandidates.slice(0, 80).forEach((candidate) => console.warn(`  ${candidate}`));
  if (hardcodedCandidates.length > 80) console.warn(`  …and ${hardcodedCandidates.length - 80} more`);
}

if (missingBurmese.length || missingEnglish.length || duplicateEnglish.length || duplicateBurmese.length || unresolvedKeys.length) process.exitCode = 1;
