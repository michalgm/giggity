const assert = require('assert');
const app = require('../.././backend/app');

describe('\'registration\' service', () => {
  it('registered the service', () => {
    const service = app.service('registration');

    assert.ok(service, 'Registered the service');
  });
});
