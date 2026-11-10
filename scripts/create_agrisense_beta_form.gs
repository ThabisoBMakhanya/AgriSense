/**
 * Finds the existing "Beta testing form" in the signed-in user's Google Drive,
 * fills in missing questions, and can be run again without duplicating items.
 */
function createAgriSenseBetaForm() {
  const form = findBetaTestingForm();
  const appUrl = 'https://agrisense-puce.vercel.app';
  form.setTitle('Beta testing form');
  form.setDescription('Thanks for helping test AgriSense. Share what worked and what needs improvement. Please do not include passwords or other sensitive information.');
  form.setCollectEmail(false);
  form.setProgressBar(true);
  form.setConfirmationMessage('Thank you for testing AgriSense. Open the app again here: ' + appUrl);

  addBetaItem(form, 'Your name', () => form.addTextItem()
    .setTitle('Your name')
    .setRequired(true));

  addBetaItem(form, 'WhatsApp number (optional)', () => form.addTextItem()
    .setTitle('WhatsApp number (optional)')
    .setHelpText('Share this only if you are happy for the AgriSense team to contact you.')
    .setRequired(false));

  addBetaItem(form, 'Which best describes you?', () => form.addMultipleChoiceItem()
    .setTitle('Which best describes you?')
    .setChoiceValues(['I am a farmer', 'I work with farmers', 'I am interested in farming', 'Other'])
    .setRequired(true));

  addBetaItem(form, 'Which device did you use?', () => form.addMultipleChoiceItem()
    .setTitle('Which device did you use?')
    .setChoiceValues(['Android phone', 'iPhone', 'Tablet', 'Computer'])
    .setRequired(true));

  addBetaItem(form, 'What activities are part of your farm or interest?', () => form.addCheckboxItem()
    .setTitle('What activities are part of your farm or interest?')
    .setChoiceValues(['Crops or vegetables', 'Livestock', 'Poultry', 'Fish or bees', 'Other'])
    .setRequired(false));

  addBetaItem(form, 'Which AgriSense features did you try?', () => form.addCheckboxItem()
    .setTitle('Which AgriSense features did you try?')
    .setChoiceValues([
      'Weather and farm advice',
      'Farm profile with multiple crops or animals',
      'Farm task calendar',
      'Input inventory',
      'Harvest by field',
      'Pest and disease reports',
      'Livestock or poultry records'
    ])
    .setRequired(true));

  addBetaItem(form, 'How easy was AgriSense to use?', () => form.addScaleItem()
    .setTitle('How easy was AgriSense to use?')
    .setBounds(1, 5)
    .setLabels('Very difficult', 'Very easy')
    .setRequired(true));

  addBetaItem(form, 'What worked well?', () => form.addParagraphTextItem()
    .setTitle('What worked well?')
    .setRequired(false));

  addBetaItem(form, 'What was confusing, broken, or difficult to use?', () => form.addParagraphTextItem()
    .setTitle('What was confusing, broken, or difficult to use?')
    .setHelpText('If something went wrong, tell us what you tapped and what happened.')
    .setRequired(false));

  addBetaItem(form, 'What feature or improvement would help you most?', () => form.addParagraphTextItem()
    .setTitle('What feature or improvement would help you most?')
    .setRequired(false));

  addBetaItem(form, 'May we contact you about your feedback?', () => form.addMultipleChoiceItem()
    .setTitle('May we contact you about your feedback?')
    .setChoiceValues(['Yes', 'No'])
    .setRequired(true));

  addBetaItem(form, 'Open AgriSense to start testing', () => form.addSectionHeaderItem()
    .setTitle('Open AgriSense to start testing')
    .setHelpText(appUrl));

  Logger.log('Tester link: ' + form.getPublishedUrl());
  Logger.log('Edit form: ' + form.getEditUrl());
}

function findBetaTestingForm() {
  const files = DriveApp.getFilesByName('Beta testing form');
  const forms = [];
  while (files.hasNext()) {
    const file = files.next();
    if (!file.isTrashed() && file.getMimeType() === 'application/vnd.google-apps.form') forms.push(file);
  }
  if (forms.length === 0) {
    throw new Error('No Google Form named "Beta testing form" was found in this Google account. Open the same account in Drive and check the form name.');
  }
  if (forms.length > 1) {
    throw new Error('More than one Google Form is named "Beta testing form". Rename the form you want to keep so this name is unique, then run again.');
  }
  return FormApp.openById(forms[0].getId());
}

function addBetaItem(form, title, createItem) {
  const alreadyExists = form.getItems().some((item) => item.getTitle() === title);
  if (!alreadyExists) createItem();
}
