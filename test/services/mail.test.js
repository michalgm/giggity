const assert = require('assert');
const app = require('../.././backend/app');

describe('\'mail\' service', () => {
  it('registered the service', () => {
    const service = app.service('mail');

    assert.ok(service, 'Registered the service');
  });
});
