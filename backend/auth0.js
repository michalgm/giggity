const ManagementClient = require("auth0").ManagementClient;
const configuration = require("@feathersjs/configuration");
// const { authentication: { auth0 } } = configuration()();
console.log("bloop");
console.log(configuration()());
const {
  authentication: {
    auth0: { domain, clientID: clientId, clientSecret },
  },
} = configuration()();
// console.log(auth0)

const auth0 = new ManagementClient({
  domain,
  clientId,
  clientSecret,
  scope: "read:clients update:clients read:client_keys",
});

const getClient = async () => {
  try {
    const { callbacks } = await auth0.getClient({ client_id: clientId });
    callbacks.push("http://blo.localhost:3030/auth/auth0/callback");
    await auth0.updateClient({ client_id: clientId }, { callbacks });
    const client = await auth0.getClient({ client_id: clientId });

    console.log(client);
  } catch (err) {
    console.error(err);
  }
};

getClient();
