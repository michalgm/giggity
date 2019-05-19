const { removeRelated } = require('../../hooks/customHooks');
const errors = require('@feathersjs/errors');
const { restrictToRole } = require('../../hooks/customHooks');

const checkOwnership = context => {
  return context;
  if (!context.params.user || !context.params.user.project) {
    return context;
  }
  const project = context.params.user.project;
  if (
    (context.id && context.id !== project) ||
    (!context.id && context.data && context.data._id !== project)
  ) {
    throw new errors.BadRequest('You cannot access a project you are not a member of');
  }
}

module.exports = {
  before: {
    all: [checkOwnership],
    find: [],
    get: [],
    create: [restrictToRole('Admin')],
    update: [],
    patch: [restrictToRole('Admin')],
    remove: [restrictToRole('Admin')]
  },

  after: {
    all: [],
    find: [],
    get: [],
    create: [],
    update: [],
    patch: [],
    remove: [
      removeRelated('api/gigs', 'project'),
      removeRelated('api/members', 'project')
    ]
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
