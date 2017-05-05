<?php

namespace {

    use App\GenericModel;
    use Illuminate\Database\Migrations\Migration;

    class FixDataFormat extends Migration
    {
        /**
         * Run the migrations.
         *
         * @return void
         */
        public function up()
        {
            //get all validations
            $validations = GenericModel::whereTo('validations')->all();

            $validationRules = [];

            //fill validationRules array
            foreach ($validations as $validation) {
                if (!empty($validation['fields'])) {
                    $validationRules[$validation['resource']] = $validation['fields'];
                }
            }

            //loop through all db records for each validation resource
            foreach ($validationRules as $resource => $fields) {
                $collectionRecords = GenericModel::whereTo($resource)->all();
                foreach ($collectionRecords as $record) {
                    $singleRecordRules = $validationRules[$resource];
                    $checkRecordFields = array_intersect_key($record->getAttributes(), $fields);
                    //Validate single record
                    $validator = \Validator::make(
                        $checkRecordFields,
                        $singleRecordRules
                    );

                    //if validator fails update record in database with fixed data format
                    if ($validator->fails()) {
                        $failedRules = $validator->failed();
                        foreach ($failedRules as $fieldName => $rule) {
                            foreach ($rule as $key => $value) {
                                if ($key === 'Required') {
                                    $record->setAttribute($fieldName, 'Validation_failed_required_field');
                                }
                                if ($key === 'String') {
                                    $record->setAttribute($fieldName, (string)$checkRecordFields[$fieldName]);
                                }
                            }
                        }
                    }

                    //bypass laravel numeric and integer validation cause if value is numeric or integer inside string,
                    // validation will pass
                    foreach ($checkRecordFields as $ruleFieldName => $valueToCheck) {
                        if ($singleRecordRules[$ruleFieldName] === 'numeric' || $singleRecordRules[$ruleFieldName]
                            === 'required|numeric'
                        ) {
                            $floatValue = $this->checkFloat($valueToCheck);
                            if ($floatValue !== false) {
                                $record->setAttribute($ruleFieldName, $floatValue);
                            }
                        }

                        if ($singleRecordRules[$ruleFieldName] === 'integer' || $singleRecordRules[$ruleFieldName]
                            === 'required|integer'
                        ) {
                            $integerValue = $this->checkInteger($valueToCheck);
                            if ($integerValue !== false) {
                                $record->setAttribute($ruleFieldName, $integerValue);
                            }
                        }
                    }

                    //save record
                    $record->markAsDirty()
                        ->save();
                }
            }
        }

        /**
         * Reverse the migrations.
         *
         * @return void
         */
        public function down()
        {
            //
        }

        /**
         * Helper method to return float value
         * @param $input
         * @return bool|float
         */
        private function checkFloat($input)
        {
            if (!is_float($input)) {
                return (float)$input;
            }

            return false;
        }

        /**
         * Helper method to return integer value
         * @param $input
         * @return bool|int
         */
        private function checkInteger($input)
        {
            if (!is_integer($input)) {
                return (int)$input;
            }

            return false;
        }
    }
}
