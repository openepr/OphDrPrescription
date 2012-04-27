<?php
/**
 * OpenEyes
 *
 * (C) Moorfields Eye Hospital NHS Foundation Trust, 2008-2011
 * (C) OpenEyes Foundation, 2011-2012
 * This file is part of OpenEyes.
 * OpenEyes is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 * OpenEyes is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License along with OpenEyes in a file titled COPYING. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package OpenEyes
 * @link http://www.openeyes.org.uk
 * @author OpenEyes <info@openeyes.org.uk>
 * @copyright Copyright (c) 2008-2011, Moorfields Eye Hospital NHS Foundation Trust
 * @copyright Copyright (c) 2011-2012, OpenEyes Foundation
 * @license http://www.gnu.org/licenses/gpl-3.0.html The GNU General Public License V3.0
 */

/**
 * The followings are the available columns in table 'et_ophdrprescription_details':
 * @property string $id
 * @property integer $event_id
 * @property string $comments
 *
 * The followings are the available model relations:
 * @property Event $event
 */
class Element_OphDrPrescription_Details extends BaseEventTypeElement {
	
	/**
	 * Returns the static model of the specified AR class.
	 * @return ElementOperation the static model class
	 */
	public static function model($className = __CLASS__) {
		return parent::model($className);
	}

	/**
	 * @return string the associated database table name
	 */
	public function tableName() {
		return 'et_ophdrprescription_details';
	}

	/**
	 * @return array validation rules for model attributes.
	 */
	public function rules() {
		// NOTE: you should only define rules for those attributes that
		// will receive user inputs.
		return array(
			array('event_id, comments', 'safe'),
			//array('', 'required'),
			// The following rule is used by search().
			// Please remove those attributes that should not be searched.
			array('id, event_id, comments', 'safe', 'on' => 'search'),
		);
	}
	
	/**
	 * @return array relational rules.
	 */
	public function relations()
	{
		// NOTE: you may need to adjust the relation name and the related
		// class name for the relations automatically generated below.
		return array(
			'element_type' => array(self::HAS_ONE, 'ElementType', 'id','on' => "element_type.class_name='".get_class($this)."'"),
			'eventType' => array(self::BELONGS_TO, 'EventType', 'event_type_id'),
			'event' => array(self::BELONGS_TO, 'Event', 'event_id'),
			'user' => array(self::BELONGS_TO, 'User', 'created_user_id'),
			'usermodified' => array(self::BELONGS_TO, 'User', 'last_modified_user_id'),
			'items' => array(self::HAS_MANY, 'OphDrPrescription_Item', 'prescription_id'),
		);
	}

	/**
	 * @return array customized attribute labels (name=>label)
	 */
	public function attributeLabels()
	{
		return array(
		);
	}

	/**
	 * Retrieves a list of models based on the current search/filter conditions.
	 * @return CActiveDataProvider the data provider that can return the models based on the search/filter conditions.
	 */
	public function search()
	{
		// Warning: Please modify the following code to remove attributes that
		// should not be searched.

		$criteria = new CDbCriteria;

		$criteria->compare('id', $this->id, true);
		$criteria->compare('event_id', $this->event_id, true);
		$criteria->compare('comments', $this->comments, true);
		
		return new CActiveDataProvider(get_class($this), array(
			'criteria' => $criteria,
		));
	}
	
	public function getCommonDrugList() {
		$firm = Firm::model()->findByPk(Yii::app()->session['selected_firm_id']);
		$subspecialty_id = $firm->serviceSubspecialtyAssignment->subspecialty_id;
		$site_id = Yii::app()->request->cookies['site_id']->value;
		$params = array(':subSpecialtyId' => $subspecialty_id, ':siteId' => $site_id);
	
		return CHtml::listData(Yii::app()->db->createCommand()
				->select('drug.id, drug.name')
				->from('drug')
				->join('site_subspecialty_drug','site_subspecialty_drug.drug_id = drug.id')
				->where('site_subspecialty_drug.subspecialty_id = :subSpecialtyId AND site_subspecialty_drug.site_id = :siteId', $params)
				->order('drug.name')
				->queryAll(), 'id', 'name');
	}

	public function getDrugDefaults() {
		$ids = array();
		foreach ($this->getCommonDrugList() as $id => $drug) {
			$ids[] = $id;
		}
		return $ids;
	}

	/**
	 * Save prescription items
	 * @todo This probably doesn't belong here, but there doesn't seem to be an easy way
	 * of doing it through the controller at the moment
	 */
	protected function afterSave() {
		if(isset($_POST['prescription_items_valid']) && $_POST['prescription_items_valid']) {
			
			// Get a list of existing item ids so we can keep track of what's been removed
			$existing_item_ids = array();
			foreach($this->items as $item) {
				$existing_item_ids[$item->id] = $item->id;
			}
			
			// Process (any) posted prescription items
			$new_items = (isset($_POST['prescription_item'])) ? $_POST['prescription_item'] : array();
			foreach($new_items as $item) {
				if(isset($item['id']) && isset($existing_item_ids[$item['id']])) {
					// Item is being updated
					$item_model = OphDrPrescription_Item::model()->findByPk($item['id']);
					unset($existing_item_ids[$item['id']]);
				} else {
					// Item is new
					$item_model = new OphDrPrescription_Item();
					$item_model->prescription_id = $this->id;
					$item_model->drug_id = $item['drug_id'];
				}
				$item_model->save();
			}

			// Delete remaining (removed) items
			OphDrPrescription_Item::model()->deleteByPk(array_values($existing_item_ids));
			
		}
		
		return parent::afterSave();
	}	
	
}
