const assert = require('assert');
const app = require('../.././backend/app');

describe('\'user_access\' service', () => {
  it('registered the service', () => {
    const service = app.service('userAccess');

    assert.ok(service, 'Registered the service');
  });
});