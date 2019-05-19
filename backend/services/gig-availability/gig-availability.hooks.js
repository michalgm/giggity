const { authenticate } = require('@feathersjs/authentication').hooks;
const { restrictToRole, restrictFields } = require('../../hooks/customHooks');


module.exports = {
  before: {
    all: [authenticate('jwt')],
    find: [],
    get: [],
    create: [restrictToRole('Manager'), restrictFields({
      'Manager': ['project', 'member', 'gig', 'comments'],
      'self': ['project', 'member', 'gig'],
    })],
    update: [],
    patch: [],
    remove: []
  },

  after: {
    all: [],
    find: [],
    get: [],
    create: [],
    update: [],
    patch: [],
    remove: []
  },

  error: {
    all: [],
    find: [],
    get: [],
    create: [],
    update: [],
    patch: [],
    remove: []
  }
};
