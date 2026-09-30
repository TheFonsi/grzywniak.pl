import { randomBytes, scryptSync, timingSafeEqual } from 'node:crypto';

export const previewPasswordHash = (password, salt = randomBytes(16).toString('hex')) => ({
  previewAuthSalt: salt,
  previewAuthHash: scryptSync(password, salt, 32).toString('hex'),
});

export const previewPasswordMatches = (project, authorization) => {
  if (!project.previewAuthSalt || !project.previewAuthHash || typeof authorization !== 'string' || !authorization.startsWith('Basic ')) return false;
  let decoded;
  try { decoded = Buffer.from(authorization.slice(6), 'base64').toString('utf8'); } catch { return false; }
  const split = decoded.indexOf(':');
  if (split < 1 || decoded.slice(0, split) !== project.previewAuthUser) return false;
  const supplied = scryptSync(decoded.slice(split + 1), project.previewAuthSalt, 32);
  const expected = Buffer.from(project.previewAuthHash, 'hex');
  return supplied.length === expected.length && timingSafeEqual(supplied, expected);
};
