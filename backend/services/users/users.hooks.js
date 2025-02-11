const { authenticate } = require('@feathersjs/authentication').hooks;
const { hashPassword, protect } = require('@feathersjs/authentication-local').hooks;
const errors = require('@feathersjs/errors');
const { restrictToRole, generateCode, restrictFields } = require('../../hooks/customHooks');
const bcrypt = require("bcryptjs");

const switchProject = context => {
  if (context.params.provider && context.data && context.data.project && context.params.user) {
    const { projects } = context.params.user;
    if (!Object.keys(projects).includes(context.data.project)) {
      throw new errors.BadRequest(`User is not a member of the project ${context.data.project}`)
    } else {
      context.params.user.project = context.data.project;
      context.app.channels.forEach(channel => {
        if (channel.match('/projects/') || channel.match('/gigs/')) {
          context.app.channel(channel).leave(context.params.connection);
        }
      });
      context.app.channel(`/projects/${context.data.project}`).join(context.params.connection);
    }
  }
  return context;
}

const createUser = async context => {
  if (!context.params.provider) {
    return;
  }
  context.data.accessCode = await generateCode();
  context.data.password = context.data.accessCode;
  if (!context.data.id) {
    delete context.data.id;
  }

  const user = (await context.service.find({ query: { email: context.data.email } }))[0];
  if (user) {
    await context.service.patch(user._id, { password: context.data.accessCode });
    context.result = {
      _id: user._id,
      email: user.email,
      name: user.name,
      accessCode: context.data.accessCode
    }
  }

  return context;
}

const customizeAuthResponse = async context => {
  // if (context.data.google) {
  //   context.data.email = context.data.google.profile.emails[0].value;
  //   context.data.name = context.data.google.profile.displayName;
  //   context.data.photo = context.data.google.profile.photos.length ? context.data.google.profile.photos[0].value : '';
  //   context.data.googleId = context.data.google.profile.id;
  // }
  if (context.data.auth0) {
    const user = await context.app.service('api/users').get(context.id);
    const data = context.data.auth0.profile;
    if (!user.auth0Id) {
      context.data.email = data.emails[0].value;
      context.data.name = data.displayName;
      context.data.photo = data.picture;
      context.data.auth0Id = data.id;
    }
  }

  return context;
}

const createMember = async (context) => {
  const member = (await context.app.service('api/members').find({ query: { user: context.result._id, project: context.data.project } }))[0];
  if (!member) {
    await context.app.service('api/members').create({ project: context.data.project, user: context.result._id, role: context.data.role });
  }
}

const sendInviteEmail = async (context) => {
  if (!context.app.get('mail').enabled) {
    return context;
  }
  const user = context.result;
  const project = await context.app.service('api/projects').get(context.data.project);
  await context.app.service('api/mail').create({
    template: 'invite',
    message: {
      to: user.email
    },
    locals: {
      user,
      project,
    }
  });
}

const passwordHasher = (password) => bcrypt.hash(password, 12);

module.exports = {
  before: {
    all: [authenticate('jwt')],
    find: [],
    get: [],
    create: [restrictToRole('Admin'), createUser, customizeAuthResponse, hashPassword({hash: passwordHasher})],
    update: [],
    patch: [restrictToRole('Admin'), customizeAuthResponse, hashPassword({hash:passwordHasher}), restrictFields({ self: ['accessCode'] }), switchProject],
    remove: []
  },

  after: {
    all: [protect('password', 'auth0Id')],
    find: [],
    get: [],
    create: [createMember, sendInviteEmail],
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
