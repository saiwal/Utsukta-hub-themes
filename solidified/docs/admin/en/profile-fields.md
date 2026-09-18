# Profile Fields

The Profile Fields section lets you define **custom profile fields** that appear on all user profiles across your hub. These extend the default profile fields (name, description, hometown, etc.) with site-specific information.

[IMAGE: Profile fields section showing existing fields and add form]

## What Custom Profile Fields Are

Custom profile fields are extra input fields that show up in every user's profile edit screen and on their public profile page. Examples:

- "Occupation"
- "Preferred pronoun"
- "Programming languages"
- "Research interests"

These are useful for themed or specialist communities (academic hubs, professional networks, hobbyist groups).

## Adding a Field

Adding a field takes **two steps** — defining it, then enabling it.

1. Click **Add field** (or fill in the form at the bottom of the list).
2. Provide:
   - **Field nickname** — the internal name (e.g. `occupation`); this is what you enable in step 4
   - **Field name** — the label shown to users (e.g. "Occupation")
   - **Field type** — text, textarea, checkbox or select
   - **Help text** — optional hint shown under the input
3. Save.
4. Add the field's **nickname** to the **Basic Profile Fields** or **Advanced Profile Fields**
   box above, then click **Submit**.

Step 4 is not optional: a field that isn't in either list stays invisible — no input in the
profile editor and nothing on the profile page. (Advanced fields only show for channels with
the "Advanced profiles" feature enabled; Basic fields show for everyone.)

[IMAGE: Add profile field form with name, type, and order fields]

## Editing and Removing Fields

Click **Edit** next to a field to change its label or type. Click **Delete** to remove it.

> ⚠ Removing a custom profile field also removes all user data stored in that field. This is irreversible.

## User Experience

Once enabled, users fill the field in at **Profiles → edit** (the full profile editor), under
"Additional information". Filled values appear on the channel's profile page and profile card.
Existing users' profiles show the field as blank until they fill it in.

Values are stored per profile, so a channel with multiple profiles can give a different answer
on each.
